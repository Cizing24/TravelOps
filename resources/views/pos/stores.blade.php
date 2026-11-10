<x-layout title="門市 POS">
    <h1 class="mb-4">門市 POS</h1>
    <ul class="data-list card">
        @forelse($stores as $store)
            <li><a class="underline" href="{{ route('pos.index', $store) }}">{{ $store->name }}</a></li>
        @empty
            <li class="muted">目前沒有授權門市。</li>
        @endforelse
    </ul>
</x-layout>
