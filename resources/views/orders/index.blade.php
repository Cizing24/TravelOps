<x-layout title="我的訂單">
    <h1>我的訂單</h1>
    <p class="muted mt-2 mb-6">查看付款、開券與退款進度。</p>
    <ul class="grid gap-3">
        @forelse($orders as $order)
            <li class="card row">
                <div><h2 class="text-xl">{{ $order->item?->name }}</h2><p class="muted text-sm"><x-time :value="$order->created_at"/> · {{ $order->item?->quantity }} 張</p></div>
                <div class="flex flex-wrap items-center gap-3"><x-status :value="$order->status"/><strong>{{ \App\Domain\Money::twd($order->total) }}</strong><a class="button secondary" href="{{ route('orders.show', $order->reference) }}">查看訂單</a></div>
            </li>
        @empty
            <li class="card">目前沒有訂單。<a class="underline" href="{{ route('products.index') }}">前往選購</a></li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $orders->links() }}</div>
</x-layout>
