<div class="mt-6 grid gap-4 md:grid-cols-2"
    @if(in_array($order->status, ['pending_payment','paid','fulfilling'], true) || in_array($order->refund?->status, ['requested', ...\App\Application\RefundOrder::BLOCK_REDEMPTION], true)) wire:poll.5s @endif>
    <section class="card">
        <h2>交易進度</h2>
        @php
            $issuance = $order->issuance?->status;
            $refund = $order->refund?->status;
            $step = match ($order->status) {
                'pending_payment' => 1,
                'paid', 'fulfilling' => 2,
                'fulfilled' => 3,
                default => null,
            };
            $progressNote = match (true) {
                $refund === 'processed' => '退款已完成，原票券憑證已撤銷。',
                in_array($refund, \App\Application\RefundOrder::BLOCK_REDEMPTION, true) => '退款處理期間暫停核銷，可在訂單明細查看進度。',
                $order->status === 'expired' => '已超過付款期限。若仍需票券，請回目錄重新選購。',
                $order->status === 'cancelled' => '訂單已取消。若仍需票券，請回目錄重新選購。',
                $order->payment?->status === 'failed' => '付款未成功。請查看付款期限與下方可執行的操作。',
                $issuance === 'permanently_failed' => '付款已確認，開券需人工處理。請在本頁查閱進度。',
                $issuance === 'retryable_failed' => '付款已確認，系統會重試開券。',
                $issuance === 'awaiting_callback' => '付款已確認，正在等待模擬供應商回覆。',
                $step === 1 => '等待付款結果。付款確認後，系統會自動開券。',
                $step === 2 => '付款已確認，請等待開券結果。',
                $step === 3 => '已開立票券，請查看個別票券的可用權益與效期。',
                default => '請查看下方狀態與可執行的操作。',
            };
        @endphp
        @if($step !== null && $order->payment?->status !== 'failed' && $issuance !== 'permanently_failed' && !in_array($refund, \App\Application\RefundOrder::BLOCK_REDEMPTION, true))
            <ol class="steps mt-4" aria-label="購票步驟">
                @foreach(['建立訂單', '確認付款', '開立票券', '查看可用權益'] as $index => $label)
                    <li @if($index === $step) aria-current="step" @elseif($index < $step) data-state="done" @endif>
                        <span class="text-sm">{{ $index + 1 }}. {{ $label }}</span>
                        <span class="block text-xs">{{ $index === $step ? '目前' : ($index < $step ? '已完成' : '尚未到達') }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
        <p class="muted mt-3" role="status">{{ $progressNote }}</p>
        <dl class="data-list mt-2" aria-live="polite">
            <div class="row"><dt>訂單</dt><dd><x-status :value="$order->status"/></dd></div>
            @if($order->payment)<div class="row"><dt>付款</dt><dd><x-status :value="$order->payment->status"/></dd></div>@endif
            @if($order->issuance)<div class="row"><dt>開券</dt><dd><x-status :value="$order->issuance->status"/></dd></div>@endif
        </dl>
        @if($order->tickets->isNotEmpty())
            <ul class="data-list mt-4">@foreach($order->tickets as $ticket)<li><a class="underline" href="{{ route('tickets.show', $ticket) }}">查看票券 {{ $ticket->sequence }}</a></li>@endforeach</ul>
        @elseif($step !== null)
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
                <div class="row"><dt>退款</dt><dd><x-status :value="$refund"/> {{ $order->refund->reason }}</dd></div>
            @endif
        </dl>
        @if($refund === 'requested') <p class="muted mt-2 text-sm">已送交營運人員，請在這裡查閱審核結果。</p> @endif
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
                <p class="muted mt-2 text-sm">這項操作只發送測試結果。延遲情境約 15 秒後回呼。</p>
            @endif
            <form method="post" action="{{ route('orders.cancel', $order->reference) }}" class="mt-3">@csrf <button class="button secondary" type="submit">取消未付款訂單</button></form>
        @elseif(!$order->refund && \App\Domain\OrderState::captured($order->status))
            <p class="muted mt-3 text-sm">退款申請由營運人員審核。核准後會等待模擬退款結果。</p>
            <form method="post" action="{{ route('orders.refund', $order->reference) }}" class="mt-4">@csrf <button class="button secondary" type="submit">申請整筆退款</button></form>
        @endif
    </section>
</div>
