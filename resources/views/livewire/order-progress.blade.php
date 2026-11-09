<div class="mt-6 grid gap-4 md:grid-cols-2"
    @if(in_array($order->status, ['pending_payment','paid','fulfilling'], true) || $order->refund?->status === 'requested') wire:poll.5s @endif>
    <section class="card">
        <h2>交易進度</h2>
        <dl class="data-list mt-2" aria-live="polite">
            <div class="row"><dt>訂單</dt><dd><x-status :value="$order->status"/></dd></div>
            @if($order->payment)<div class="row"><dt>付款</dt><dd><x-status :value="$order->payment->status"/></dd></div>@endif
            @if($order->issuance)<div class="row"><dt>開券</dt><dd><x-status :value="$order->issuance->status"/></dd></div>@endif
        </dl>
        @if($order->tickets->isNotEmpty())
            <ul class="data-list mt-4">@foreach($order->tickets as $ticket)<li><a class="underline" href="{{ route('tickets.show', $ticket) }}">查看票券 {{ $ticket->sequence }}</a></li>@endforeach</ul>
        @else
            <p class="muted mt-3">開券完成後，票券會顯示在這裡。</p>
        @endif
    </section>
    <section class="card">
        <h2>訂單明細</h2>
        <dl class="data-list mt-2">
            <div class="row"><dt>數量</dt><dd>{{ $order->item?->quantity }} 張</dd></div>
            <div class="row"><dt>總金額</dt><dd>{{ \App\Domain\Money::twd($order->total) }}</dd></div>
            <div class="row"><dt>付款截止（臺灣時間）</dt><dd><x-time :value="$order->pay_by"/></dd></div>
            @if($order->refund)
                <div class="row"><dt>退款</dt><dd><x-status :value="$order->refund->status"/> {{ $order->refund->reason }}</dd></div>
            @endif
        </dl>
        @if(\App\Domain\OrderState::awaitingPayment($order->status))
            @if($demoPayment)
                <form method="post" action="{{ route('orders.simulate', $order->reference) }}" class="mt-4">
                    @csrf
                    <label for="scenario">模擬付款情境</label>
                    <select id="scenario" name="scenario">
                        <option value="success">付款成功</option><option value="failure">付款失敗</option>
                        <option value="delayed">15 秒後回呼</option><option value="duplicate">重複成功回呼</option>
                        <option value="out_of_order">成功後收到失敗回呼</option>
                    </select>
                    <button type="submit" class="button mt-3">執行模擬付款</button>
                </form>
            @endif
            <form method="post" action="{{ route('orders.cancel', $order->reference) }}" class="mt-3">@csrf <button class="button secondary" type="submit">取消未付款訂單</button></form>
        @elseif(!$order->refund && \App\Domain\OrderState::captured($order->status))
            <form method="post" action="{{ route('orders.refund', $order->reference) }}" class="mt-4">@csrf <button class="button secondary" type="submit">申請整筆退款</button></form>
        @endif
    </section>
</div>
