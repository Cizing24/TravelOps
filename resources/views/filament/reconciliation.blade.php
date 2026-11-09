<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="reconcile" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block" for="recon-day">對帳起日（臺灣時間）</label>
                <input id="recon-day" type="date" wire:model.live="day" required>
                @error('from') <span role="alert">{{ $message }}</span> @enderror
                @error('day') <span role="alert">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block" for="recon-until">對帳迄日（選填）</label>
                <input id="recon-until" type="date" wire:model.live="until">
                @error('to') <span role="alert">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block" for="recon-supplier">供應商</label>
                <select id="recon-supplier" wire:model.live="supplier">
                    <option value="">全部，含付款及退款</option>
                    @foreach($suppliers as $provider)
                        <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                    @endforeach
                </select>
                @error('supplier') <span role="alert">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block" for="recon-reference">訂單參照</label>
                <input id="recon-reference" type="search" wire:model.live.debounce.400ms="reference" maxlength="36">
            </div>
            <x-filament::button type="submit" wire:loading.attr="disabled">重新取得帳冊並對帳</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="最近執行紀錄">
        <ul class="space-y-2">
            @forelse($runs as $run)
                <li><x-time :value="$run->created_at"/> · {{ __('status.'.$run->status) }} · 比對 {{ $run->processed }} 筆，差異 {{ $run->anomalies }} 筆</li>
            @empty
                <li>此日期尚無對帳紀錄。</li>
            @endforelse
        </ul>
    </x-filament::section>

    <x-filament::section heading="差異清單（最多 100 筆）">
        <div class="grid gap-4">
            @forelse($findings as $finding)
                <article class="rounded-lg border border-gray-300 p-4" wire:key="finding-{{ $finding->id }}">
                    <h2 class="font-semibold">{{ __('status.'.$finding->kind) }} · {{ __('status.'.$finding->severity) }}</h2>
                    <p>{{ $finding->day }} · {{ __('status.'.$finding->source) }} · {{ __('status.'.$finding->status) }}</p>
                    <p class="break-all">追蹤編號 {{ $finding->correlation_id }}</p>
                    <a class="break-all underline" href="{{ url($finding->source === 'refund' ? '/ops/refunds' : '/ops/orders').'?search='.urlencode($finding->order_reference) }}">查詢{{ $finding->source === 'refund' ? '退款' : '訂單' }} {{ $finding->order_reference }}</a>
                    <p>內部 {{ $finding->evidence['internal_status'] ?? '無紀錄' }} / {{ $finding->evidence['internal_value'] ?? '—' }}</p>
                    <p>外部 {{ $finding->evidence['external_status'] ?? '無紀錄' }} / {{ $finding->evidence['external_value'] ?? '—' }}</p>
                    @if($finding->status === 'open')
                        <form wire:submit="resolve({{ $finding->id }})" class="mt-2 grid gap-2">
                            <label for="recon-reason-{{ $finding->id }}">處理原因</label>
                            <textarea id="recon-reason-{{ $finding->id }}" wire:model="reasons.{{ $finding->id }}" maxlength="500" required></textarea>
                            @error('reasons.'.$finding->id) <p role="alert">{{ $message }}</p> @enderror
                            <x-filament::button type="submit" wire:loading.attr="disabled">標記已處理</x-filament::button>
                        </form>
                    @elseif($finding->reason)
                        <p>處理原因 {{ $finding->reason }}</p>
                        <p>處理人 {{ $finding->reviewer?->name ?? '—' }} · <x-time :value="$finding->reviewed_at"/></p>
                    @endif
                </article>
            @empty
                <p>此條件目前沒有差異。</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
