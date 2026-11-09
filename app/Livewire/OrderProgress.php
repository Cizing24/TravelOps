<?php

namespace App\Livewire;

use App\Infrastructure\MockGateway;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class OrderProgress extends Component
{
    #[Locked]
    public string $reference;

    public function render(): View
    {
        $order = Order::where('reference', $this->reference)->with(['payment', 'issuance', 'tickets', 'item', 'refund'])->firstOrFail();
        Gate::authorize('view', $order);

        return view('livewire.order-progress', ['order' => $order, 'demoPayment' => app(MockGateway::class)->enabled()]);
    }
}
