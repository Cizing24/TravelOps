<?php

namespace App\Http\Controllers;

use App\Application\RefundOrder;
use App\Models\Order;
use App\Models\Ticket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class TicketController
{
    public function index(Request $request): View
    {
        Gate::authorize('create', Order::class);

        return view('tickets.index', [
            'tickets' => Ticket::whereHas('order', fn ($query) => $query->where('user_id', $request->user()?->id)->where('source_channel', 'online'))
                ->with('order.item')->withCount(['entitlements', 'entitlements as redeemed_count' => fn ($query) => $query->where('status', 'redeemed')])
                ->latest('id')->paginate(15),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('view', $ticket);
        $ticket->load(['order.item', 'order.refund', 'entitlements.redemption']);
        $redemptionPaused = in_array($ticket->order?->refund?->status, RefundOrder::BLOCK_REDEMPTION, true);
        $qr = null;
        if ($ticket->status === 'active' && ! $ticket->isExpired() && ! $redemptionPaused) {
            $writer = new Writer(new ImageRenderer(new RendererStyle(216), new SvgImageBackEnd));
            $qr = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($ticket->code));
        }

        return view('tickets.show', compact('ticket', 'qr', 'redemptionPaused'));
    }
}
