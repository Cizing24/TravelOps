<x-layout title="門市日結">
    <a class="underline" href="{{ route('pos.index', $store) }}">返回 {{ $store->name }}</a>
    <h1 class="my-4">{{ $store->name }}日結</h1>
    <section class="card">
        <form class="flex flex-wrap items-end gap-3" method="get" action="{{ route('pos.closing', $store) }}">
            <div>
                <label for="day">營業日（臺灣時間）</label>
                <input type="date" id="day" name="day" value="{{ $day }}" required>
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
                <div class="row"><dt>當日銷售</dt><dd>0 筆</dd></div>
            @endforelse
            <div class="row"><dt>該日交易已退款</dt><dd>{{ $refunds }} 筆</dd></div>
            <div class="row"><dt>該日交易開券異常</dt><dd>{{ $exceptions }} 筆</dd></div>
        </dl>
    </section>
</x-layout>
