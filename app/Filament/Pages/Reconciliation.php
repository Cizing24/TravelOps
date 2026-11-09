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

final class Reconciliation extends Page
{
    protected string $view = 'filament.reconciliation';

    protected static ?string $navigationLabel = '交易對帳';

    protected static ?string $title = '交易對帳';

    public string $day = '';

    public ?int $supplier = null;

    /** @var array<int, string> */
    public array $reasons = [];

    public static function canAccess(): bool
    {
        return Gate::allows('operate');
    }

    public function mount(): void
    {
        $this->day = now('Asia/Taipei')->toDateString();
    }

    public function reconcile(Reconcile $reconcile): void
    {
        Gate::authorize('operate');
        try {
            $run = $reconcile->run($this->day, $this->supplier ?: null, auth()->user());
            Notification::make()->title('對帳完成，共 '.$run->anomalies.' 筆差異')->success()->send();
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

        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'runs' => ReconciliationRun::where('day', $this->day)->when($this->supplier,
                fn ($query, $id) => $query->where('supplier_id', $id))->latest('id')->limit(10)->get(),
            'findings' => ReconciliationFinding::where('day', $this->day)->when($this->supplier,
                fn ($query, $id) => $query->where('supplier_id', $id))->orderByRaw("status <> 'open'")->latest('id')->limit(100)->get(),
        ];
    }
}
