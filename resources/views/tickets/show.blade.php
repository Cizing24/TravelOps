<x-layout title="票券詳情">
    @if($ticket->order?->store_id)
        <a class="muted underline" href="{{ route('pos.show', [$ticket->order->store_id, $ticket->order->reference]) }}">← 門市銷售明細</a>
    @else
        <a class="muted underline" href="{{ route('tickets.index') }}">← 我的票券</a>
    @endif
    <h1 class="my-4">{{ $ticket->order?->item?->name }}</h1>
    <div class="grid items-start gap-4 md:grid-cols-[3fr_2fr]">
        <section class="card">
            <div class="row"><h2>場館權益</h2><x-status :value="$ticket->isExpired() && $ticket->status === 'active' ? 'expired' : $ticket->status"/></div>
            @if($redemptionPaused)<p class="mt-2"><x-status value="redemption_paused"/></p>@endif
            <dl class="summary data-list mt-4">
                <div class="row"><dt>有效期限（臺灣時間）</dt><dd>@if($ticket->expires_at)<x-time :value="$ticket->expires_at"/>@else 首次核銷後 {{ $ticket->valid_days }} 天 @endif</dd></div>
                <div class="row"><dt>已使用權益</dt><dd>{{ $ticket->entitlements->where('status', 'redeemed')->count() }}／{{ $ticket->entitlements->count() }} 個場館</dd></div>
                <div class="row"><dt>剩餘可用</dt><dd>{{ $qr ? $ticket->entitlements->where('status', 'unused')->count() : 0 }} 個場館</dd></div>
            </dl>
            <ul class="data-list mt-4">
                @foreach($ticket->entitlements as $entitlement)
                    <li class="row">
                        <strong>{{ $entitlement->venue_name }}</strong>
                        <div><x-status :value="$entitlement->status === 'unused' ? ($ticket->isExpired() ? 'expired' : ($redemptionPaused ? 'redemption_paused' : 'unused')) : $entitlement->status"/>
                        @if($entitlement->redemption)<x-time :value="$entitlement->redemption->redeemed_at"/>@endif</div>
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="card">
            <h2>場館核銷憑證</h2>
            @if($qr)
                <p class="muted mt-2 text-sm">抵達場館後，向工作人員出示 QR Code 或下方文字代碼。</p>
                <img src="{{ $qr }}" width="216" height="216" alt="場館核銷 QR Code" class="mx-auto my-4 h-auto max-w-full">
                <code class="credential block break-all">{{ $ticket->code }}</code>
            @elseif($redemptionPaused)
                <p class="muted mt-3">退款處理期間暫停核銷。請回交易明細查看退款狀態。</p>
            @else
                <p class="muted mt-3">目前無可用核銷憑證。</p>
            @endif
        </section>
    </div>
</x-layout>
