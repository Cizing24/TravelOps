<x-filament-panels::page>
    <x-filament::section heading="報表篩選">
        <form method="get">
            <div class="ops-filter">
                <div class="ops-field">
                    <label for="report-from">起日（臺灣時間）</label>
                    <x-filament::input.wrapper><x-filament::input id="report-from" name="from" type="date" :value="$filters['from']" required /></x-filament::input.wrapper>
                </div>
                <div class="ops-field">
                    <label for="report-to">迄日（臺灣時間）</label>
                    <x-filament::input.wrapper><x-filament::input id="report-to" name="to" type="date" :value="$filters['to']" required /></x-filament::input.wrapper>
                </div>
                <div class="ops-field">
                    <label for="report-channel">通路</label>
                    <x-filament::input.wrapper><x-filament::input.select id="report-channel" name="channel">
                        <option value="">全部通路</option>
                        @foreach(['online', 'pos'] as $channel)
                            <option value="{{ $channel }}" @selected(($filters['channel'] ?? null) === $channel)>{{ __('status.'.$channel) }}</option>
                        @endforeach
                    </x-filament::input.select></x-filament::input.wrapper>
                </div>
                <div class="ops-field">
                    <label for="report-store">門市</label>
                    <x-filament::input.wrapper><x-filament::input.select id="report-store" name="store">
                        <option value="">全部門市</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" @selected(($filters['store'] ?? null) == $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </x-filament::input.select></x-filament::input.wrapper>
                </div>
                <div class="ops-field">
                    <label for="report-supplier">供應商</label>
                    <x-filament::input.wrapper><x-filament::input.select id="report-supplier" name="supplier">
                        <option value="">全部供應商</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(($filters['supplier'] ?? null) == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </x-filament::input.select></x-filament::input.wrapper>
                </div>
            </div>
            <div class="ops-actions ops-help">
                <x-filament::button type="submit">套用報表篩選</x-filament::button>
                <x-filament::button tag="a" color="gray" :href="route('reports.csv', $filters)" aria-describedby="report-export">匯出 CSV</x-filament::button>
            </div>
        </form>
        <p id="report-export" class="ops-help">先套用篩選再匯出。畫面顯示新臺幣元，CSV 金額以新臺幣分為單位（100 分等於 1 元）。</p>
    </x-filament::section>

    <x-filament::section heading="營運摘要">
        <dl class="ops-metrics">
            @foreach($metrics as $label => $value)
                @php($money = str_ends_with($label, '（分）'))
                <div>
                    <dt>{{ $money ? str_replace('（分）', '', $label) : $label }}</dt>
                    <dd>{{ $money ? ($value < 0 ? '−' : '').\App\Domain\Money::twd(abs((int) $value)) : number_format($value, is_float($value) ? 2 : 0) }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="ops-help">共用即時可售量是目前餘額，可依供應商篩選。門市及通路篩選適用於交易與流水。</p>
    </x-filament::section>

    <x-filament::section heading="通路與門市銷售">
        <ul class="ops-rows">
            @forelse($sales as $sale)
                <li>{{ __('status.'.$sale->channel) }} {{ $sale->store_name }} · {{ $sale->count }} 筆 · {{ \App\Domain\Money::twd((int) $sale->amount) }}</li>
            @empty
                <li>此期間沒有符合條件的成功交易。</li>
            @endforelse
        </ul>
    </x-filament::section>

    <x-filament::section heading="供應商履約">
        <ul class="ops-rows">
            @forelse($providers as $provider)
                <li>
                    <strong>{{ $provider->name }}</strong>
                    <p>成功 {{ $provider->succeeded }}/{{ $provider->total }}（{{ round(100 * $provider->succeeded / $provider->total, 1) }}%）
                        · 失敗 {{ $provider->failed }} · 後續嘗試 {{ $provider->retries }}
                        · 平均確認 {{ $provider->latency === null ? '—' : round((float) $provider->latency, 1).' 秒' }}</p>
                </li>
            @empty
                <li>此期間沒有符合條件的開券紀錄。</li>
            @endforelse
        </ul>
    </x-filament::section>

    <x-filament::section heading="庫存流水">
        <ul class="ops-rows">
            @forelse($movements as $movement)
                <li>{{ __('status.'.$movement->kind) }} · {{ $movement->count }} 筆 · 可售量差額 {{ $movement->delta }} 張</li>
            @empty
                <li>此期間沒有庫存異動。</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-panels::page>
