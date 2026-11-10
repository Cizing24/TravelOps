<x-layout :title="$product->name">
    <a class="muted underline" href="{{ route('products.index') }}">← 票券目錄</a>
    <div class="mt-5 grid gap-5 md:grid-cols-[2fr_1fr]">
        <article class="card">
            <p class="kicker muted">{{ __('status.'.$product->kind) }}</p>
            <h1 class="mt-2">{{ $product->name }}</h1>
            <p class="muted mt-4">{{ $product->description }}</p>
            <h2 class="mt-7">可使用場館</h2>
            <ul class="data-list mt-2">@foreach($product->venues as $venue)<li>{{ $venue->name }}</li>@endforeach</ul>
            <p class="muted mt-4">首次成功核銷後 {{ $product->valid_days }} 天內有效，每個場館限用一次。</p>
        </article>
        <aside class="card self-start">
            <h2>購買票券</h2>
            <p class="mt-2 text-2xl font-bold">{{ \App\Domain\Money::twd($product->price) }} <span class="muted text-sm font-normal">／張</span></p>
            <p class="muted mb-4">剩餘 {{ $product->inventory?->available ?? 0 }} 張</p>
            @if(($product->inventory?->available ?? 0) < 1)
                <p class="badge">已售完</p>
            @elseif(auth()->user()?->role === 'customer')
                <form method="post" action="{{ route('orders.store') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key') ?: \Illuminate\Support\Str::uuid() }}">
                    <label for="quantity">購買數量</label>
                    <input id="quantity" name="quantity" type="number" min="1" max="{{ min(10, $product->inventory->available) }}" value="{{ old('quantity', 1) }}" required>
                    <button class="button mt-4 w-full" type="submit">建立訂單</button>
                </form>
                <p class="muted mt-3 text-sm">
                    下單後保留庫存 15 分鐘。
                    @if(app(\App\Infrastructure\MockGateway::class)->enabled())
                        請在訂單頁選擇模擬付款情境。
                    @endif
                </p>
            @elseif(!auth()->check())
                <a class="button w-full" href="{{ route('login') }}">登入以購買</a>
            @else
                <p class="muted">請使用旅客帳號購買。</p>
            @endif
        </aside>
    </div>
</x-layout>
