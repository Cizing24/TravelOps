<x-layout title="門市日結">
    <a class="underline" href="{{ route('pos.index', $store) }}">返回 {{ $store->name }}</a>
    <h1 class="my-4">{{ $store->name }}日結</h1>
    <section class="card">
        <p class="muted mb-4">依付款日查看本店各付款方式的筆數與金額。</p>
        <form class="flex flex-wrap items-end gap-3" method="get" action="{{ route('pos.closing', $store) }}">
            <div>
                <label for="closing-from">營業起日（臺灣時間）</label>
                <input type="date" id="closing-from" name="from" value="{{ $from }}" required>
            </div>
            <div>
                <label for="closing-to">營業迄日（臺灣時間）</label>
                <input type="date" id="closing-to" name="to" value="{{ $to }}" required>
            </div>
            <button class="button" type="submit">查詢日結</button>
        </form>
        <dl class="data-list mt-4">
            @forelse($sales as $sale)
                <div class="row">
                    <dt>{{ __('status.'.$sale->payment_method) }}</dt>
                    <dd>{{ $sale->count }} 筆 · {{ \App\Domain\Money::twd((int) $sale->gross) }}</dd>
                </div>
            @empty
                <div class="row"><dt>期間銷售</dt><dd>0 筆</dd></div>
            @endforelse
            <div class="row"><dt>期間交易已退款</dt><dd>{{ $refunds }} 筆</dd></div>
            <div class="row"><dt>期間交易開券異常</dt><dd>{{ $exceptions }} 筆</dd></div>
        </dl>
    </section>
</x-layout>
