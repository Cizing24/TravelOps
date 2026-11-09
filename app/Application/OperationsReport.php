<?php

namespace App\Application;

use App\Domain\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class OperationsReport
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function summarize(array $input): array
    {
        Gate::authorize('operate');
        $today = now('Asia/Taipei')->toDateString();
        $filters = Validator::validate($input + ['from' => $today, 'to' => $today], [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'channel' => 'nullable|in:online,pos',
            'store' => 'nullable|integer|exists:stores,id',
            'supplier' => 'nullable|integer|exists:suppliers,id',
        ]);
        $start = CarbonImmutable::parse($filters['from'], 'Asia/Taipei')->startOfDay();
        $end = CarbonImmutable::parse($filters['to'], 'Asia/Taipei')->startOfDay()->addDay();
        if ($start->addDays(92)->lt($end)) {
            throw new RuleViolation('report_range', '報表日期範圍最多 92 天。', 422);
        }
        $start = $start->utc();
        $end = $end->utc();
        $orderFacts = DB::table('orders as o')->join('order_items as i', 'i.order_id', '=', 'o.id')
            ->when($filters['channel'] ?? null, fn (Builder $q, string $channel) => $q->where('o.source_channel', $channel))
            ->when($filters['store'] ?? null, fn (Builder $q, mixed $id) => $q->where('o.store_id', $id))
            ->when($filters['supplier'] ?? null, fn (Builder $q, mixed $id) => $q->where('i.supplier_id', $id));
        $paid = (clone $orderFacts)->join('payments as p', 'p.order_id', '=', 'o.id')
            ->whereIn('p.status', ['succeeded', 'refunded'])->where('p.paid_at', '>=', $start)->where('p.paid_at', '<', $end);
        $gross = (int) (clone $paid)->sum('p.amount');
        $sales = (clone $paid)->leftJoin('stores as store', 'store.id', '=', 'o.store_id')
            ->selectRaw('o.source_channel AS channel, store.name AS store_name, COUNT(*) AS count, SUM(p.amount) AS amount')
            ->groupBy('o.source_channel', 'store.id', 'store.name')->orderBy('o.source_channel')->orderBy('store.name')->get();
        $refunded = (int) (clone $orderFacts)->join('refunds as r', 'r.order_id', '=', 'o.id')->where('r.status', 'processed')
            ->whereRaw('COALESCE(r.processed_at, r.updated_at) >= ?', [$start])
            ->whereRaw('COALESCE(r.processed_at, r.updated_at) < ?', [$end])->sum('r.amount');
        $failedRefunds = (clone $orderFacts)->join('refunds as r', 'r.order_id', '=', 'o.id')
            ->whereIn('r.status', ['failed', 'requires_review'])->where('r.updated_at', '>=', $start)->where('r.updated_at', '<', $end)->count();
        $entitlements = (clone $orderFacts)->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_entitlements as e', 'e.ticket_id', '=', 't.id')->where('t.created_at', '>=', $start)->where('t.created_at', '<', $end);
        $issuedRights = (clone $entitlements)->count();
        $usedRights = $entitlements->where('e.status', 'redeemed')->count();
        $inventory = DB::table('inventory_balances as b')->join('products as product', 'product.id', '=', 'b.product_id')
            ->when($filters['supplier'] ?? null, fn (Builder $q, mixed $id) => $q->where('product.supplier_id', $id));
        $movement = DB::table('inventory_movements as m')->join('products as product', 'product.id', '=', 'm.product_id')
            ->leftJoin('orders as o', 'o.id', '=', 'm.order_id')->leftJoin('order_items as i', 'i.order_id', '=', 'o.id')
            ->where('m.created_at', '>=', $start)->where('m.created_at', '<', $end)
            ->when($filters['supplier'] ?? null, fn (Builder $q, mixed $id) => $q->whereRaw('COALESCE(i.supplier_id, product.supplier_id) = ?', [$id]))
            ->when($filters['store'] ?? null, fn (Builder $q, mixed $id) => $q->where('o.store_id', $id))
            ->when($filters['channel'] ?? null, fn (Builder $q, string $channel) => $q->where('o.source_channel', $channel));
        $movements = $movement->selectRaw('m.kind, COUNT(*) AS count, SUM(m.delta) AS delta')->groupBy('m.kind')->get();
        $reservations = (clone $orderFacts)->join('inventory_reservations as res', 'res.order_id', '=', 'o.id')
            ->where('res.created_at', '>=', $start)->where('res.created_at', '<', $end);
        $reserved = (int) (clone $reservations)->where('res.status', 'held')->sum('res.quantity');
        $sold = (int) $reservations->where('res.status', 'sold')->sum('res.quantity');
        $providers = (clone $orderFacts)->join('outbox_events as e', 'e.order_id', '=', 'o.id')
            ->join('suppliers as s', 's.id', '=', 'i.supplier_id')->where('e.created_at', '>=', $start)->where('e.created_at', '<', $end)
            ->selectRaw("s.name, COUNT(*) AS total, SUM(e.status = 'succeeded') AS succeeded,
                SUM(e.status = 'permanently_failed') AS failed, SUM(GREATEST(e.attempts - 1, 0)) AS retries,
                AVG(CASE WHEN e.status = 'succeeded' THEN TIMESTAMPDIFF(SECOND, e.created_at, e.updated_at) END) AS latency")
            ->groupBy('s.id', 's.name')->orderBy('s.name')->get();
        $findings = DB::table('reconciliation_findings as f')->whereBetween('f.day', [$filters['from'], $filters['to']])
            ->when($filters['supplier'] ?? null, fn (Builder $q, mixed $id) => $q->where('f.supplier_id', $id));
        if (! empty($filters['store']) || ! empty($filters['channel'])) {
            $findings->whereIn('f.order_reference', (clone $orderFacts)->select('o.reference'));
        }
        $findings = $findings->selectRaw('f.status, COUNT(*) AS count')->groupBy('f.status')->pluck('count', 'status');

        return [
            'filters' => $filters, 'sales' => $sales, 'providers' => $providers, 'movements' => $movements,
            'metrics' => [
                '成功交易筆數' => $sales->sum('count'), '成功交易額（分）' => $gross,
                '成功退款額（分）' => $refunded, '淨銷售額（分）' => $gross - $refunded,
                '失敗或待人工退款筆數' => $failedRefunds, '開立權益數' => $issuedRights,
                '已核銷權益數' => $usedRights, '權益核銷率（%）' => $issuedRights ? round(100 * $usedRights / $issuedRights, 2) : 0,
                '預留票量' => $reserved, '已售票量' => $sold, '共用即時可售量' => (int) $inventory->sum('b.available'),
                '待處理對帳差異' => (int) ($findings['open'] ?? 0), '人工處理對帳差異' => (int) ($findings['reviewed'] ?? 0),
                '已收斂對帳差異' => (int) ($findings['resolved'] ?? 0),
            ],
        ];
    }

    public static function csvCell(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && preg_match('/\A(?:[\x00-\x20\p{Z}]*[=+\-@＝＋－＠]|[\t\r\n])/u', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
