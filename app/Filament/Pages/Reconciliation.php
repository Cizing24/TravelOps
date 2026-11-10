<?php

namespace App\Filament\Pages;

use App\Application\Reconcile;
use App\Domain\RuleViolation;
use App\Models\ReconciliationFinding;
use App\Models\ReconciliationRun;
use App\Models\Supplier;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class Reconciliation extends Page
{
    protected string $view = 'filament.reconciliation';

    protected static ?string $navigationLabel = '交易對帳';

    protected static ?string $title = '交易對帳';

    public string $day = '';

    public string $until = '';

    public string $supplier = '';

    public string $reference = '';

    /** @var array<int, string> */
    public array $reasons = [];

    public static function canAccess(): bool
    {
        return Gate::allows('operate');
    }

    public function mount(): void
    {
        $day = request()->string('day')->toString();
        $this->day = preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $day) ? $day : now('Asia/Taipei')->toDateString();
        $supplier = request()->string('supplier')->toString();
        $this->supplier = ctype_digit($supplier) ? $supplier : '';
        $reference = request()->string('reference')->toString();
        $this->reference = Str::isUuid($reference) ? $reference : '';
    }

    public function reconcile(Reconcile $reconcile): void
    {
        Gate::authorize('operate');
        try {
            $supplier = ctype_digit($this->supplier) ? (int) $this->supplier : null;
            $runs = $reconcile->runRange($this->day, $this->until ?: $this->day, $supplier, auth()->user());
            Notification::make()->title('對帳完成，共 '.collect($runs)->sum('anomalies').' 筆差異')->success()->send();
        } catch (RuleViolation $error) {
            Notification::make()->title($error->getMessage())->danger()->send();
        }
    }

    public function resolve(int $id, Reconcile $reconcile): void
    {
        Gate::authorize('operate');
        $reason = trim($this->reasons[$id] ?? '');
        if ($reason === '' || mb_strlen($reason) > 500) {
            $this->addError('reasons.'.$id, '請輸入 1 至 500 字的處理原因。');

            return;
        }
        try {
            $reconcile->resolve(auth()->user(), $id, $reason);
            unset($this->reasons[$id]);
            Notification::make()->title('已記錄處理結果')->success()->send();
        } catch (RuleViolation $error) {
            Notification::make()->title($error->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        Gate::authorize('operate');
        $range = [$this->day, $this->until ?: $this->day];
        if (Validator::make($range, ['*' => 'date_format:Y-m-d'])->fails()) {
            $range = [now('Asia/Taipei')->toDateString(), now('Asia/Taipei')->toDateString()];
        }

        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'runs' => ReconciliationRun::whereBetween('day', $range)->when($this->supplier,
                fn ($query, $id) => $query->where('supplier_id', $id))->latest('id')->limit(10)->get(),
            'findings' => ReconciliationFinding::with('reviewer')->whereBetween('day', $range)->when($this->supplier,
                fn ($query, $id) => $query->where('supplier_id', $id))->when($this->reference,
                    fn ($query, $reference) => $query->where('order_reference', $reference))
                ->orderByRaw("status <> 'open'")->latest('id')->limit(100)->get(),
        ];
    }
}
