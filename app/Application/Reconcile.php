<?php

namespace App\Application;

use App\Domain\RuleViolation;
use App\Models\ReconciliationFinding;
use App\Models\ReconciliationRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class Reconcile
{
    public function run(string $day, ?int $supplierId, ?User $actor): ReconciliationRun
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('operate');
        }
        Validator::validate(['day' => $day, 'supplier' => $supplierId], [
            'day' => 'required|date_format:Y-m-d', 'supplier' => 'nullable|integer|exists:suppliers,id',
        ]);
        abort_unless(app()->environment('local', 'testing'), 404);
        $lock = Cache::lock('travelops-reconciliation:'.$day, 120);
        if (! $lock->get()) {
            throw new RuleViolation('run_busy', '此營業日已有對帳執行中。');
        }
        try {
            $run = ReconciliationRun::create([
                'day' => $day, 'supplier_id' => $supplierId, 'user_id' => $actor?->id, 'status' => 'running',
            ]);
            try {
                DB::transaction(fn () => $this->compare($run, $day, $supplierId), 5);
            } catch (Throwable $error) {
                $run->update(['status' => 'failed', 'finished_at' => now()]);
                throw $error;
            }

            return $run;
        } finally {
            $lock->release();
        }
    }

    public function resolve(User $actor, int $findingId, string $reason): void
    {
        Gate::forUser($actor)->authorize('operate');
        $reason = trim($reason);
        Validator::validate(['reason' => $reason], ['reason' => 'required|string|max:500']);
        DB::transaction(function () use ($actor, $findingId, $reason): void {
            $finding = ReconciliationFinding::query()->lockForUpdate()->findOrFail($findingId);
            if ($finding->status !== 'open') {
                throw new RuleViolation('already_resolved', '這筆差異已處理。');
            }
            $finding->update(['status' => 'reviewed', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'reason' => $reason]);
            Audit::record('reconciliation.reviewed', $actor->id, null, [
                'finding_id' => $findingId, 'reason' => $reason, 'before' => 'open', 'after' => 'reviewed',
            ], $finding->correlation_id);
        }, 5);
    }

    private function compare(ReconciliationRun $run, string $day, ?int $supplier): void
    {
        $start = CarbonImmutable::parse($day, 'Asia/Taipei')->startOfDay();
        $end = $start->addDay()->utc();
        $start = $start->utc();
        $known = [];
        $processed = 0;
        $matched = 0;
        foreach ($supplier === null ? ['supplier', 'payment', 'refund'] : ['supplier'] as $source) {
            [$internalQuery, $externalQuery, $dateColumn] = $this->queries($source, $supplier);
            $local = (clone $internalQuery)->where($dateColumn, '>=', $start)->where($dateColumn, '<', $end)
                ->limit(5001)->get()->keyBy('request_key');
            $remote = (clone $externalQuery)->where('s.created_at', '>=', $start)->where('s.created_at', '<', $end)
                ->limit(5001)->get()->keyBy('request_key');
            if ($local->count() > 5000 || $remote->count() > 5000) {
                throw new RuleViolation('too_many_records', '單日交易超過展示對帳上限，請縮小供應商範圍。');
            }
            $local = $local->union($internalQuery->whereIn('request_key', $remote->keys()->all())->get()->keyBy('request_key'));
            $remote = $remote->union($externalQuery->whereIn('s.request_key', $local->keys()->all())->get()->keyBy('request_key'));
            $duplicateRefs = $remote->groupBy('order_reference')->filter(fn ($rows): bool => $rows->count() > 1)->keys()->flip();
            foreach ($local->keys()->merge($remote->keys())->unique() as $key) {
                $before = $local->get($key);
                $after = $remote->get($key);
                $fact = $before ?? $after;
                $reference = $fact->order_reference;
                $remoteStatus = $after === null ? null : ($after->ready_at !== null && CarbonImmutable::parse($after->ready_at)->isFuture()
                    ? 'pending' : $after->result);
                $aged = $before !== null && $before->result === 'pending'
                    && CarbonImmutable::parse($before->event_at)->lt(now()->subMinutes(30));
                $kind = match (true) {
                    $source === 'supplier' && $duplicateRefs->has($reference) => 'duplicate',
                    $before === null => 'remote_only',
                    $after === null && $aged => 'aged_pending',
                    $after === null && $before->result === 'succeeded' => 'missing_remote',
                    $after !== null && (int) $before->quantity !== (int) $after->quantity => 'amount_or_quantity',
                    $aged => 'aged_pending',
                    $after !== null && $before->result !== $remoteStatus => 'status_mismatch',
                    default => null,
                };
                $processed++;
                if ($kind === null) {
                    $matched++;

                    continue;
                }
                $fingerprint = hash('sha256', implode('|', [$day, $source, $key, $kind]));
                $known[] = $fingerprint;
                $finding = ReconciliationFinding::firstOrNew(['fingerprint' => $fingerprint]);
                $finding->fill([
                    'reconciliation_run_id' => $run->id, 'source' => $source, 'kind' => $kind, 'day' => $day,
                    'order_reference' => $reference, 'correlation_id' => $key, 'supplier_id' => $fact->supplier_id,
                    'severity' => $kind === 'aged_pending' ? 'warning' : 'high',
                    'evidence' => [
                        'internal_status' => $before->result ?? null, 'external_status' => $remoteStatus,
                        'internal_value' => $before->quantity ?? null, 'external_value' => $after->quantity ?? null,
                    ],
                ]);
                if (! $finding->exists || $finding->status === 'resolved') {
                    $finding->status = 'open';
                }
                $finding->save();
            }
        }
        ReconciliationFinding::where('day', $day)->where('status', 'open')
            ->when($supplier !== null, fn ($query) => $query->where('supplier_id', $supplier))
            ->whereNotIn('fingerprint', $known)->update(['status' => 'resolved']);
        $run->update(['status' => 'succeeded', 'processed' => $processed, 'matched' => $matched, 'anomalies' => count($known), 'finished_at' => now()]);
        Audit::record('reconciliation.completed', $run->user_id, null, ['run_id' => $run->id, 'anomalies' => count($known)]);
        if ($known !== []) {
            app(NotificationCenter::class)->operations('reconciliation', $day.':'.($supplier ?? 'all'), null);
        }
    }

    /** @return array{Builder, Builder, string} */
    private function queries(string $source, ?int $supplier): array
    {
        return match ($source) {
            'supplier' => [
                DB::query()->fromSub(DB::table('outbox_events as e')->join('orders as o', 'o.id', '=', 'e.order_id')
                    ->join('order_items as i', 'i.order_id', '=', 'o.id')
                    ->when($supplier !== null, fn (Builder $q) => $q->where('i.supplier_id', $supplier))
                    ->selectRaw("e.trace_id AS request_key, o.reference AS order_reference, i.quantity, i.supplier_id,
                        e.created_at AS event_at, CASE WHEN e.cancelled_at IS NOT NULL THEN 'cancelled'
                        WHEN e.cancel_available_at IS NOT NULL OR e.cancel_error IS NOT NULL THEN 'pending'
                        WHEN e.status = 'succeeded' THEN 'succeeded'
                        WHEN e.status IN ('permanently_failed', 'cancelled') THEN 'failed' ELSE 'pending' END AS result"), 'facts'),
                DB::table('supplier_statements as s')->when($supplier !== null, fn (Builder $q) => $q->where('s.supplier_id', $supplier))
                    ->selectRaw("s.request_key, s.order_reference, s.quantity, s.supplier_id, s.ready_at,
                        CASE WHEN s.cancelled_at IS NOT NULL THEN 'cancelled' ELSE 'succeeded' END AS result"),
                'event_at',
            ],
            'payment' => [
                DB::query()->fromSub(DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')
                    ->selectRaw("p.idempotency_key AS request_key, o.reference AS order_reference, p.amount AS quantity,
                        NULL AS supplier_id, p.paid_at AS event_at, CASE WHEN p.status IN ('succeeded','refunded')
                        THEN 'succeeded' ELSE 'pending' END AS result"), 'facts'),
                DB::table('payment_statements as s')
                    ->selectRaw("s.request_key, s.order_reference, s.amount AS quantity, NULL AS supplier_id, NULL AS ready_at, 'succeeded' AS result"),
                'event_at',
            ],
            default => [
                DB::query()->fromSub(DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')
                    ->whereNotIn('r.status', ['requested', 'rejected'])
                    ->selectRaw("r.idempotency_key AS request_key, o.reference AS order_reference, r.amount AS quantity,
                        NULL AS supplier_id, r.created_at AS event_at, CASE WHEN r.status = 'processed' THEN 'succeeded'
                        WHEN r.status = 'failed' THEN 'failed' ELSE 'pending' END AS result"), 'facts'),
                DB::table('refund_statements as s')
                    ->selectRaw('s.request_key, s.order_reference, s.amount AS quantity, NULL AS supplier_id, s.ready_at, s.result'),
                'event_at',
            ],
        };
    }
}
