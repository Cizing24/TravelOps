<x-layout title="門市 POS">
    <h1>門市 POS</h1>
    <p class="muted mt-2 mb-4">選擇當班門市，進入銷售工作台與近期交易。</p>
    <ul class="data-list card">
        @forelse($stores as $store)
            <li><a class="button secondary" href="{{ route('pos.index', $store) }}">{{ $store->name }}</a></li>
        @empty
            <li class="muted">目前沒有授權門市。</li>
        @endforelse
    </ul>
</x-layout>
