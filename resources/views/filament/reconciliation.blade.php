<x-filament-panels::page>
    <x-filament::section heading="對帳範圍">
        <form wire:submit="reconcile">
            <div class="ops-filter">
                <div class="ops-field">
                    <label for="recon-day">對帳起日（臺灣時間）</label>
                    <x-filament::input.wrapper><x-filament::input id="recon-day" type="date" wire:model.live="day" required /></x-filament::input.wrapper>
                    @error('from') <span role="alert">{{ $message }}</span> @enderror
                    @error('day') <span role="alert">{{ $message }}</span> @enderror
                </div>
                <div class="ops-field">
                    <label for="recon-until">對帳迄日（選填）</label>
                    <x-filament::input.wrapper><x-filament::input id="recon-until" type="date" wire:model.live="until" /></x-filament::input.wrapper>
                    @error('to') <span role="alert">{{ $message }}</span> @enderror
                </div>
                <div class="ops-field">
                    <label for="recon-supplier">供應商</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="recon-supplier" wire:model.live="supplier">
                            <option value="">全部，含付款及退款</option>
                            @foreach($suppliers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('supplier') <span role="alert">{{ $message }}</span> @enderror
                </div>
                <div class="ops-field">
                    <label for="recon-reference">訂單參照（只篩選差異清單）</label>
                    <x-filament::input.wrapper><x-filament::input id="recon-reference" type="search" wire:model.live.debounce.400ms="reference" maxlength="36" /></x-filament::input.wrapper>
                </div>
            </div>
            <div class="ops-actions ops-help">
                <x-filament::button type="submit" wire:loading.attr="disabled">重新取得帳冊並對帳</x-filament::button>
                <p role="status" wire:loading wire:target="reconcile">正在比對內外部帳冊…</p>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section heading="最近執行紀錄">
        <ul class="ops-rows">
            @forelse($runs as $run)
                <li><x-time :value="$run->created_at"/> · {{ __('status.'.$run->status) }} · 比對 {{ $run->processed }} 筆，差異 {{ $run->anomalies }} 筆</li>
            @empty
                <li>此日期尚無對帳紀錄。</li>
            @endforelse
        </ul>
    </x-filament::section>

    <x-filament::section heading="差異清單（最多 100 筆）">
        <div class="ops-list">
            @forelse($findings as $finding)
                <article class="ops-finding" wire:key="finding-{{ $finding->id }}">
                    <h3>{{ __('status.'.$finding->kind) }}</h3>
                    <div class="ops-actions ops-help">
                        <x-filament::badge :color="match($finding->severity) { 'high' => 'danger', 'warning' => 'warning', default => 'info' }">{{ __('status.'.$finding->severity) }}</x-filament::badge>
                        <x-filament::badge :color="$finding->status === 'open' ? 'warning' : 'success'">{{ __('status.'.$finding->status) }}</x-filament::badge>
                        <span>{{ $finding->day }} · {{ __('status.'.$finding->source) }}</span>
                    </div>
                    <dl class="ops-evidence">
                        @foreach(['internal' => '內部帳冊', 'external' => '外部帳冊'] as $side => $label)
                            @php
                                $value = $finding->evidence[$side.'_value'] ?? null;
                                $state = $finding->evidence[$side.'_status'] ?? null;
                            @endphp
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd>{{ $value === null ? '—' : ($finding->source === 'supplier' ? number_format((int) $value).' 張' : \App\Domain\Money::twd((int) $value)) }}</dd>
                                <dd>{{ $state === null ? '無紀錄' : __('status.'.$state) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <details class="ops-help">
                        <summary>訂單參照與追蹤編號</summary>
                        <p class="ops-identifier">追蹤編號 {{ $finding->correlation_id }}</p>
                        <a href="{{ url($finding->source === 'refund' ? '/ops/refunds' : '/ops/orders').'?search='.urlencode($finding->order_reference) }}">查詢{{ $finding->source === 'refund' ? '退款' : '訂單' }} {{ $finding->order_reference }}</a>
                    </details>
                    @if($finding->status === 'open')
                        <form wire:submit="resolve({{ $finding->id }})" class="ops-field ops-help">
                            <label for="recon-reason-{{ $finding->id }}">處理原因</label>
                            <x-filament::input.wrapper>
                                <textarea class="ops-reason" id="recon-reason-{{ $finding->id }}" wire:model="reasons.{{ $finding->id }}" placeholder="記錄已查核的差異與處理依據" maxlength="500" required></textarea>
                            </x-filament::input.wrapper>
                            @error('reasons.'.$finding->id) <p role="alert">{{ $message }}</p> @enderror
                            <div><x-filament::button type="submit" wire:loading.attr="disabled">標記已處理</x-filament::button></div>
                        </form>
                    @elseif($finding->reason)
                        <p class="ops-help">處理原因 {{ $finding->reason }}</p>
                        <p>處理人 {{ $finding->reviewer?->name ?? '—' }} · <x-time :value="$finding->reviewed_at"/></p>
                    @endif
                </article>
            @empty
                <p>此條件目前沒有差異。</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
