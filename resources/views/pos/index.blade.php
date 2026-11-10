<x-layout :title="$store->name">
    <a class="underline" href="{{ route('pos.stores') }}">所有授權門市</a>
    <h1 class="my-4">{{ $store->name }} POS</h1>
    <div class="grid gap-4 md:grid-cols-2">
        <section class="card">
            <h2>新增銷售</h2>
            <form method="post" action="{{ route('pos.sell', $store) }}" class="mt-4 grid gap-3">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $key) }}">
                <label for="product">商品與目前可售量</label>
                <select id="product" name="product_id" required>
                    <option value="">請選擇商品</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id') == $product->id) @disabled(($product->inventory?->available ?? 0) < 1)>
                            {{ $product->name }}（{{ $product->inventory?->available ?? 0 }} 張）
                        </option>
                    @endforeach
                </select>
                <label for="quantity">張數</label>
                <input id="quantity" type="number" name="quantity" min="1" max="10" value="{{ old('quantity', 1) }}" required>
                <label for="method">付款方式</label>
                <select id="method" name="method">
                    <option value="cash">現金（展示）</option>
                    <option value="mock_card" @selected(old('method') === 'mock_card')>模擬卡片</option>
                </select>
                <button class="button" type="submit">確認門市銷售</button>
            </form>
        </section>
        <section class="card">
            <h2>本店交易</h2>
            @if(auth()->user()->role === 'pos_supervisor')
                <a class="underline" href="{{ route('pos.closing', $store) }}">門市日結</a>
            @endif
            <ul class="data-list mt-4">
                @forelse($orders as $order)
                    <li class="row">
                        <a class="underline" href="{{ route('pos.show', [$store, $order->reference]) }}">{{ $order->item?->name }} × {{ $order->item?->quantity }}</a>
                        <x-status :value="$order->status"/>
                    </li>
                @empty
                    <li class="muted">本門市還沒有銷售。</li>
                @endforelse
            </ul>
            {{ $orders->links() }}
        </section>
    </div>
</x-layout>
