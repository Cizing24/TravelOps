<x-layout title="我的票券">
    <h1>我的票券</h1>
    <p class="muted mt-2 mb-6">前往場館時，開啟票券詳情出示核銷憑證。</p>
    <ul class="grid gap-3">
        @forelse($tickets as $ticket)
            <li class="card row">
                <div><h2 class="text-xl">{{ $ticket->order?->item?->name }}</h2><p class="muted">已使用 {{ $ticket->redeemed_count }} / {{ $ticket->entitlements_count }} 個場館</p></div>
                <div class="flex flex-wrap items-center gap-3"><x-status :value="$ticket->isExpired() && $ticket->status === 'active' ? 'expired' : $ticket->status"/><a class="button secondary" href="{{ route('tickets.show', $ticket) }}">查看票券</a></div>
            </li>
        @empty
            <li class="card">目前沒有票券。已付款的訂單開券後會顯示在這裡。</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $tickets->links() }}</div>
</x-layout>
