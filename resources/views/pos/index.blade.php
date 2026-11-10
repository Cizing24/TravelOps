<x-layout :title="$store->name">
    <a class="underline" href="{{ route('pos.stores') }}">所有授權門市</a>
    <header class="row my-4">
        <div><h1>{{ $store->name }} POS</h1><p class="muted mt-2">先確認品項、張數與收款方式，再送出門市交易。</p></div>
        @if(auth()->user()->role === 'pos_supervisor')
            <a class="button secondary" href="{{ route('pos.closing', $store) }}">門市日結</a>
        @endif
    </header>
    <div class="grid items-start gap-4 lg:grid-cols-[3fr_2fr]">
        <section class="card">
            <h2>新增銷售</h2>
            <form method="post" action="{{ route('pos.sell', $store) }}" class="mt-4 grid gap-4" data-sale-summary data-pending="正在確認門市銷售…">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $key) }}">
                <div>
                    <label for="product">商品</label>
                    <select id="product" name="product_id" required>
                        <option value="">請選擇商品</option>
                        @foreach($products as $product)
                            @php($available = $product->inventory?->available ?? 0)
                            <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->price }}" data-available="{{ $available }}" @selected(old('product_id') == $product->id) @disabled($available < 1)>
                                {{ $product->name }} · {{ \App\Domain\Money::twd($product->price) }}／張 · {{ $available > 0 ? '可售 '.$available.' 張' : '已售完' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @unless($products->contains(fn ($product) => ($product->inventory?->available ?? 0) > 0))
                    <p class="summary-warning">目前沒有可售商品。</p>
                @endunless
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label for="quantity">張數</label><input id="quantity" type="number" name="quantity" min="1" max="10" value="{{ old('quantity', 1) }}" required aria-describedby="sale-note"></div>
                    <div>
                        <label for="method">付款方式</label>
                        <select id="method" name="method">
                            <option value="cash">現金（展示）</option>
                            <option value="mock_card" @selected(old('method') === 'mock_card')>模擬卡片</option>
                        </select>
                    </div>
                </div>
                <section class="summary" data-summary hidden aria-labelledby="sale-summary">
                    <h3 id="sale-summary">送出前確認</h3>
                    <dl class="data-list text-sm">
                        <div class="row"><dt>商品</dt><dd data-product>—</dd></div>
                        <div class="row"><dt>單價</dt><dd data-unit>—</dd></div>
                        <div class="row"><dt>可售量</dt><dd data-stock>—</dd></div>
                        <div class="row"><dt>張數</dt><dd data-quantity>—</dd></div>
                        <div class="row"><dt>付款方式</dt><dd data-method>—</dd></div>
                        <div class="row"><dt id="sale-total">預估總金額</dt><dd><output class="price" for="product quantity" aria-labelledby="sale-total" data-total>—</output></dd></div>
                    </dl>
                    <p role="status" class="summary-warning" data-summary-hint></p>
                </section>
                <p id="sale-note" class="muted text-sm">每筆 1 至 10 張。庫存與金額以系統確認的銷售明細為準。</p>
                <button class="button" type="submit">確認門市銷售</button>
                <p class="sr-only" role="status" data-submit-status></p>
            </form>
        </section>
        <section class="card">
            <h2>本店近期交易</h2>
            <ul class="data-list mt-4">
                @forelse($orders as $order)
                    <li class="row">
                        <div><a class="underline font-bold" href="{{ route('pos.show', [$store, $order->reference]) }}">{{ $order->item?->name }} × {{ $order->item?->quantity }}</a>
                            <p class="muted text-sm"><x-time :value="$order->created_at"/></p></div>
                        <div class="text-sm"><x-status :value="$order->status"/> <strong>{{ \App\Domain\Money::twd($order->total) }}</strong></div>
                    </li>
                @empty
                    <li class="muted">確認第一筆門市銷售後，可在這裡查詢金額與開券結果。</li>
                @endforelse
            </ul>
            {{ $orders->links() }}
        </section>
    </div>
</x-layout>
