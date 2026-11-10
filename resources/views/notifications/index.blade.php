<x-layout title="通知中心">
    <h1>通知中心</h1>
    <p class="muted mt-2 mb-4">查看交易與營運更新，從通知直接前往相關紀錄。</p>
    <div class="grid items-start gap-4 md:grid-cols-[3fr_2fr]">
        <section class="card">
            <h2>收件匣</h2>
            <ul class="data-list mt-4">
                @forelse($messages as $message)
                    <li class="py-3">
                        <div class="row"><h3>{{ $message->title }}</h3>
                            <p class="muted text-sm"><x-time :value="$message->created_at"/> · <span class="badge" data-tone="{{ $message->read_at ? 'neutral' : 'info' }}">{{ $message->read_at ? '已讀' : '未讀' }}</span></p>
                        </div>
                        @if($message->ticket_id && $recipient->role === 'customer')
                            <a class="underline" href="{{ route('tickets.show', $message->ticket_id) }}">查看票券</a>
                        @elseif($message->order && $recipient->role === 'customer')
                            <a class="underline" href="{{ route('orders.show', $message->order->reference) }}">查看訂單</a>
                        @elseif($message->template === 'reconciliation')
                            <a class="underline" href="{{ url('/ops/reconciliation') }}">查看對帳差異</a>
                        @elseif($message->order && $recipient->operates())
                            <a class="underline" href="{{ url('/ops/outbox-events').'?search='.urlencode($message->order->reference) }}">查看開券結果</a>
                        @endif
                        @unless($message->read_at)
                            <form class="mt-2" method="post" action="{{ route('notifications.read', $message) }}">
                                @csrf
                                <button type="submit" class="button secondary">標記已讀</button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="muted">目前沒有通知。</li>
                @endforelse
            </ul>
            {{ $messages->links() }}
        </section>
        <section class="card">
            <h2>通知偏好</h2>
            <p class="muted mt-2">交易及必要營運事件會顯示於站內。展示通道只記錄投遞狀態。</p>
            <form method="post" action="{{ route('notifications.preferences') }}" class="mt-3 grid gap-3">
                @csrf
                <label class="check-field"><input type="checkbox" name="notification_email" value="1" @checked($recipient->notification_email)> 模擬 Email</label>
                <label class="check-field"><input type="checkbox" name="notification_line" value="1" @checked($recipient->notification_line)> 模擬 LINE</label>
                <label class="check-field"><input type="checkbox" name="remind_expiry" value="1" @checked($recipient->remind_expiry)> 票券到期提醒</label>
                <button class="button" type="submit">儲存通知偏好</button>
            </form>
        </section>
    </div>
</x-layout>
