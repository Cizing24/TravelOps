<?php

namespace App\Http\Controllers;

use App\Application\PointOfSale;
use App\Application\RefundOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PosController
{
    public function stores(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->staffsPos(), 403);

        return view('pos.stores', ['stores' => $actor->stores()->orderBy('name')->get()]);
    }

    public function index(Request $request, Store $store): View
    {
        abort_unless($this->actor($request)->staffsStore($store), 403);

        return view('pos.index', [
            'store' => $store, 'key' => (string) Str::uuid(),
            'products' => Product::where('listed', true)->with('inventory')->orderBy('name')->get(),
            'orders' => Order::where('store_id', $store->id)->with('item')->latest('id')->paginate(15),
        ]);
    }

    public function sell(Request $request, Store $store, PointOfSale $pos): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->staffsStore($store), 403);
        $data = $request->validate([
            'product_id' => 'required|integer|min:1', 'quantity' => 'required|integer|between:1,10',
            'idempotency_key' => 'required|uuid', 'method' => 'required|in:cash,mock_card',
        ]);
        $order = $pos->sell($actor, $store, (int) $data['product_id'], (int) $data['quantity'], $data['idempotency_key'], $data['method']);
        if ($request->expectsJson()) {
            return response()->json(['reference' => $order->reference, 'status' => $order->status], $order->wasRecentlyCreated ? 201 : 200);
        }

        return redirect()->route('pos.show', [$store, $order->reference])->with('notice', '門市交易已確認付款。');
    }

    public function show(Request $request, Store $store, string $reference): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->staffsStore($store), 403);
        $order = Order::where('store_id', $store->id)->where('reference', $reference)
            ->with(['item', 'tickets', 'issuance', 'refund'])->firstOrFail();

        return view('pos.sale', ['store' => $store, 'order' => $order, 'canRequestRefund' => $actor->role === 'pos_supervisor']);
    }

    public function refund(Request $request, Store $store, string $reference, RefundOrder $refunds): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->staffsStore($store) && $actor->role === 'pos_supervisor', 403);
        $order = Order::where('store_id', $store->id)->where('reference', $reference)->firstOrFail();
        $refunds->request($actor, $order);

        return redirect()->route('pos.show', [$store, $reference])->with('notice', '已送交營運人員審核退款。');
    }

    public function closing(Request $request, Store $store): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->staffsStore($store) && $actor->role === 'pos_supervisor', 403);
        $filters = $request->validate(['day' => 'nullable|date_format:Y-m-d']);
        $day = $filters['day'] ?? now('Asia/Taipei')->toDateString();
        $start = Carbon::parse($day, 'Asia/Taipei')->startOfDay();
        $orders = Order::where('store_id', $store->id)->whereHas('payment', fn ($query) => $query
            ->where('paid_at', '>=', $start->copy()->utc())
            ->where('paid_at', '<', $start->copy()->addDay()->utc()));

        return view('pos.closing', [
            'store' => $store, 'day' => $day,
            'sales' => (clone $orders)->selectRaw('payment_method, COUNT(*) AS count, SUM(total) AS gross')->groupBy('payment_method')->get(),
            'refunds' => (clone $orders)->whereHas('refund', fn ($query) => $query->where('status', 'processed'))->count(),
            'exceptions' => $orders->whereHas('issuance', fn ($query) => $query->where('status', 'permanently_failed'))->count(),
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
