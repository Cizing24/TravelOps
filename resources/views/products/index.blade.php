<x-layout title="票券目錄">
    <section class="hero">
        <div class="hero-copy">
            <p class="kicker mb-4">TravelOps / 票券目錄</p>
            <h1>挑一張票，出發。</h1>
            <p class="muted mt-4">選購單館票或多場館聯票。付款開券後，出示核銷代碼即可在合作場館使用。</p>
        </div>
    </section>
    <section aria-labelledby="catalog" class="mt-10">
        <div class="row mb-4"><h2 id="catalog">在售票券</h2><p class="muted">共 {{ $products->total() }} 項</p></div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($products as $product)
                <article class="card ticket-card">
                    <p class="kicker muted">{{ __('status.'.$product->kind) }} · {{ $product->venues_count }} 個場館</p>
                    <h3 class="mt-2">{{ $product->name }}</h3>
                    <p class="muted mt-2 mb-5 grow">{{ $product->description }}</p>
                    <div class="row"><strong>{{ \App\Domain\Money::twd($product->price) }}</strong><a class="button secondary" href="{{ route('products.show', $product->slug) }}" aria-label="查看 {{ $product->name }}">查看票券</a></div>
                </article>
            @empty
                <p class="card">目前沒有在售票券。</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    </section>
</x-layout>
