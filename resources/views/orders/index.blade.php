<x-layout title="我的訂單">
    <p class="kicker muted">旅客中心</p><h1 class="mt-2 mb-6">我的訂單</h1>
    <ul class="grid gap-3">
        @forelse($orders as $order)
            <li class="card row">
                <div><h2 class="text-xl">{{ $order->item?->name }}</h2><p class="muted break-all text-sm">{{ $order->reference }}</p></div>
                <div class="flex flex-wrap items-center gap-3"><x-status :value="$order->status"/><strong>{{ \App\Domain\Money::twd($order->total) }}</strong><a class="button secondary" href="{{ route('orders.show', $order->reference) }}">查看訂單</a></div>
            </li>
        @empty
            <li class="card">目前沒有訂單。<a class="underline" href="{{ route('products.index') }}">前往選購</a></li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $orders->links() }}</div>
</x-layout>
