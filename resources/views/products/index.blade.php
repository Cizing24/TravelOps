<x-layout title="票券目錄">
    <section class="hero">
        <div class="hero-copy">
            <h1>選一張灣岸入場券</h1>
            <p class="mt-3">從單館展覽到多場館聯票，先確認可用場館和效期，再選購適合你的票。</p>
            <p class="muted mt-3 text-sm">展示目錄 · 商品、付款與開券皆為模擬情境</p>
        </div>
    </section>
    <section aria-labelledby="catalog" class="mt-7">
        <div class="row mb-4"><h2 id="catalog">在售票券</h2><p class="muted">共 {{ $products->total() }} 項</p></div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($products as $product)
                <article class="card ticket-card">
                    <h3>{{ $product->name }}</h3>
                    <p class="mt-2 text-sm"><span class="badge">{{ __('status.'.$product->kind) }}</span> <span class="muted">涵蓋 {{ $product->venues_count }} 個場館</span></p>
                    <p class="muted mt-3 mb-5">{{ $product->description }}</p>
                    <div class="ticket-stub row">
                        <p><strong class="price">{{ \App\Domain\Money::twd($product->price) }}</strong> <span class="muted text-sm">／張</span></p>
                        <a class="button secondary" href="{{ route('products.show', $product->slug) }}" aria-label="查看 {{ $product->name }}">查看票券</a>
                    </div>
                </article>
            @empty
                <p class="card">目前沒有在售票券。</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    </section>
</x-layout>
