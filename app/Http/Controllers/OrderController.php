<?php

namespace App\Http\Controllers;

use App\Application\Checkout;
use App\Application\PaymentWebhook;
use App\Application\RefundOrder;
use App\Infrastructure\MockGateway;
use App\Jobs\DeliverMockPayment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class OrderController
{
    public function index(Request $request): View
    {
        Gate::authorize('create', Order::class);

        return view('orders.index', [
            'orders' => Order::where('user_id', $request->user()?->id)->with('item')->latest('id')->paginate(15),
        ]);
    }

    public function store(Request $request, Checkout $checkout): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Order::class);
        $data = $request->validate([
            'product_id' => 'required|integer|min:1', 'quantity' => 'required|integer|between:1,10',
            'idempotency_key' => 'required|uuid',
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $order = $checkout->place($actor, (int) $data['product_id'], (int) $data['quantity'], $data['idempotency_key']);
        if ($request->expectsJson()) {
            return response()->json(['reference' => $order->reference, 'status' => $order->status], $order->wasRecentlyCreated ? 201 : 200);
        }

        return redirect()->route('orders.show', $order->reference)->with('notice', '訂單已建立，請在期限內付款。');
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('orders.show', ['order' => $order->load('item')]);
    }

    public function cancel(Request $request, Order $order, Checkout $checkout): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $checkout->cancel($actor, $order);

        return redirect()->route('orders.show', $order->reference)->with('notice', '已取消訂單。');
    }

    public function refund(Request $request, Order $order, RefundOrder $refunds): RedirectResponse
    {
        Gate::authorize('view', $order);
        /** @var User $actor */
        $actor = $request->user();
        $refunds->request($actor, $order);

        return redirect()->route('orders.show', $order->reference)->with('notice', '已送出退款申請，請查看審核結果。');
    }

    public function simulate(Request $request, Order $order, MockGateway $gateway, PaymentWebhook $webhook): RedirectResponse
    {
        abort_unless($gateway->enabled(), 404);
        Gate::authorize('view', $order);
        $scenario = $request->validate(['scenario' => 'required|in:success,failure,delayed,duplicate,out_of_order'])['scenario'];
        $event = $gateway->event($order, $scenario === 'failure' ? 'failed' : 'succeeded');

        if ($scenario === 'delayed') {
            DeliverMockPayment::dispatch($event['body'], $event['signature'])->onConnection('redis')->delay(now()->addSeconds(15));
        } else {
            $webhook->receive($event['body'], $event['signature']);
            if ($scenario === 'duplicate') {
                $webhook->receive($event['body'], $event['signature']);
                $webhook->receive($event['body'], $event['signature']);
            }
            if ($scenario === 'out_of_order') {
                $late = $gateway->event($order, 'failed');
                $webhook->receive($late['body'], $late['signature']);
            }
        }

        return redirect()->route('orders.show', $order->reference)->with('notice', '已送出模擬付款結果。');
    }

    public function webhook(Request $request, PaymentWebhook $webhook): JsonResponse
    {
        return response()->json([
            'outcome' => $webhook->receive($request->getContent(), (string) $request->header('X-TravelOps-Signature')),
        ]);
    }
}
