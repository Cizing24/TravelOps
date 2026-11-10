<x-layout title="門市銷售明細">
    <a class="underline" href="{{ route('pos.index', $store) }}">返回 {{ $store->name }}</a>
    <h1 class="my-4">門市銷售明細</h1>
    <section class="card">
        <h2>{{ $order->item?->name }} × {{ $order->item?->quantity }}</h2>
        <p class="mt-2"><strong class="price">{{ \App\Domain\Money::twd($order->total) }}</strong> · {{ __('status.'.$order->payment_method) }}</p>
        <p class="break-all muted">{{ $order->reference }}</p>
        <p class="mt-2"><x-status :value="$order->status"/></p>
        <ul class="data-list mt-4">
            @forelse($order->tickets as $ticket)
                <li><a class="underline" href="{{ route('tickets.show', $ticket) }}">查看憑證 {{ $ticket->sequence }}</a></li>
            @empty
                <li class="muted">開券狀態 <x-status :value="$order->issuance?->status ?? 'pending'"/>
                    @if(\App\Domain\OrderState::canIssue($order->status))
                        <p class="mt-2">這筆交易已有付款紀錄。請在此查看開券結果，若需人工處理請聯絡營運人員。</p>
                    @endif
                </li>
                <li><a class="underline" href="{{ route('pos.show', [$store, $order->reference]) }}">重新整理開券結果</a></li>
            @endforelse
        </ul>
        @if($order->refund)
            <p class="mt-4">退款狀態 <x-status :value="$order->refund->status"/></p>
            @if($order->refund->reason)<p class="muted">{{ $order->refund->reason }}</p>@endif
        @elseif($canRequestRefund && \App\Domain\OrderState::captured($order->status))
            <form class="mt-4" method="post" action="{{ route('pos.refund', [$store, $order->reference]) }}">
                @csrf
                <button type="submit" class="button secondary">送出整筆退款申請</button>
            </form>
        @endif
    </section>
</x-layout>
