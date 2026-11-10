<x-filament-panels::page>
    <x-filament::section>
        <form class="flex flex-wrap items-end gap-4" method="get">
            <div><label class="block" for="report-from">起日</label><input id="report-from" name="from" type="date" value="{{ $filters['from'] }}" required></div>
            <div><label class="block" for="report-to">迄日（臺灣時間）</label><input id="report-to" name="to" type="date" value="{{ $filters['to'] }}" required></div>
            <div>
                <label class="block" for="report-channel">通路</label>
                <select id="report-channel" name="channel">
                    <option value="">全部通路</option>
                    @foreach(['online', 'pos'] as $channel)
                        <option value="{{ $channel }}" @selected(($filters['channel'] ?? null) === $channel)>{{ __('status.'.$channel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block" for="report-store">門市</label>
                <select id="report-store" name="store">
                    <option value="">全部門市</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected(($filters['store'] ?? null) == $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block" for="report-supplier">供應商</label>
                <select id="report-supplier" name="supplier">
                    <option value="">全部供應商</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(($filters['supplier'] ?? null) == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-filament::button type="submit">套用報表篩選</x-filament::button>
            <x-filament::button tag="a" color="gray" :href="route('reports.csv', $filters)">匯出 CSV</x-filament::button>
        </form>
        <p class="mt-3 text-sm">變更篩選條件後，先套用再匯出。CSV 金額以新臺幣分為單位，100 分為 1 元。</p>
    </x-filament::section>

    <x-filament::section heading="營運摘要">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($metrics as $label => $value)
                <div class="rounded-lg border border-gray-300 p-3">
                    <dt>{{ $label }}</dt><dd class="text-lg font-semibold">{{ number_format($value, is_float($value) ? 2 : 0) }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4">共用即時可售量為目前餘額。門市及通路篩選適用交易與流水；即時可售量可依供應商篩選。</p>
    </x-filament::section>

    <x-filament::section heading="通路與門市銷售">
        <ul class="space-y-2">
            @forelse($sales as $sale)
                <li>{{ __('status.'.$sale->channel) }} {{ $sale->store_name }} · {{ $sale->count }} 筆 · {{ \App\Domain\Money::twd((int) $sale->amount) }}</li>
            @empty
                <li>此期間沒有符合條件的成功交易。</li>
            @endforelse
        </ul>
    </x-filament::section>

    <x-filament::section heading="供應商履約">
        <ul class="space-y-2">
            @forelse($providers as $provider)
                <li>{{ $provider->name }} · 成功 {{ $provider->succeeded }}/{{ $provider->total }}（{{ round(100 * $provider->succeeded / $provider->total, 1) }}%）
                    · 失敗 {{ $provider->failed }} · 後續嘗試 {{ $provider->retries }}
                    · 平均確認 {{ $provider->latency === null ? '—' : round((float) $provider->latency, 1).' 秒' }}</li>
            @empty
                <li>此期間沒有符合條件的開券紀錄。</li>
            @endforelse
        </ul>
    </x-filament::section>

    <x-filament::section heading="庫存流水">
        <ul class="space-y-2">
            @forelse($movements as $movement)
                <li>{{ __('status.'.$movement->kind) }} · {{ $movement->count }} 筆 · 可售量差額 {{ $movement->delta }} 張</li>
            @empty
                <li>此期間沒有庫存異動。</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-panels::page>
