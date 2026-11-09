<x-layout title="票券詳情">
    <a class="muted underline" href="{{ route('tickets.index') }}">← 我的票券</a>
    <h1 class="my-4">{{ $ticket->order?->item?->name }}</h1>
    <div class="grid gap-4 md:grid-cols-[2fr_1fr]">
        <section class="card">
            <div class="row"><h2>場館權益</h2><x-status :value="$ticket->isExpired() && $ticket->status === 'active' ? 'expired' : $ticket->status"/></div>
            <p class="muted mt-3">有效期限（臺灣時間）
                @if($ticket->expires_at) <x-time :value="$ticket->expires_at"/> @else 首次核銷後 {{ $ticket->valid_days }} 天 @endif
            </p>
            <ul class="data-list mt-4">
                @foreach($ticket->entitlements as $entitlement)
                    <li class="row">
                        <strong>{{ $entitlement->venue_name }}</strong>
                        <div><x-status :value="$entitlement->status === 'unused' && $ticket->isExpired() ? 'expired' : $entitlement->status"/>
                        @if($entitlement->redemption)<x-time :value="$entitlement->redemption->redeemed_at"/>@endif</div>
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="card">
            <h2>場館核銷憑證</h2>
            @if($qr)
                <img src="{{ $qr }}" width="216" height="216" alt="場館核銷 QR Code" class="mx-auto my-4">
                <p class="muted text-sm">可將下方代碼交給場館人員輸入。</p>
                <code class="block break-all mt-2">{{ $ticket->code }}</code>
            @else
                <p class="muted mt-3">目前無可用核銷憑證。</p>
            @endif
        </section>
    </div>
</x-layout>
