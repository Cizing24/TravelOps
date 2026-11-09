<x-layout title="訂單詳情">
    <a class="muted underline" href="{{ route('orders.index') }}">← 我的訂單</a>
    <h1 class="mt-4">{{ $order->item?->name }}</h1>
    <p class="muted mt-2 break-all">{{ $order->reference }}</p>
    <livewire:order-progress :reference="$order->reference" />
</x-layout>
