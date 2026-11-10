<?php

namespace Tests\Integration;

use App\Application\Issuance;
use App\Application\SupplierProvider;
use App\Infrastructure\MockSupplier;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\CreatesOrders;
use Tests\TestCase;

final class SupplierIssuanceTest extends TestCase
{
    use CreatesOrders;
    use DatabaseMigrations;

    public function test_supplier_call_runs_outside_the_order_transaction(): void
    {
        $order = $this->place();
        $this->pay($order);
        $provider = new class implements SupplierProvider
        {
            public function issue(OrderItem $item, string $traceId, int $attempt): array
            {
                TestCase::assertSame(0, DB::transactionLevel());

                return ['result' => 'success', 'error' => null];
            }

            public function queryIssuance(OrderItem $item, string $traceId): ?array
            {
                return null;
            }

            public function cancelIssuance(OrderItem $item, string $traceId): bool
            {
                return true;
            }
        };

        (new Issuance($provider))->process($order->issuance()->firstOrFail()->id);

        self::assertSame('fulfilled', $order->refresh()->status);
        self::assertSame(1, $order->tickets()->count());
    }

    public function test_interrupted_worker_is_recovered_with_the_same_request_identity(): void
    {
        $order = $this->place();
        $this->pay($order);
        $event = $order->issuance()->firstOrFail();
        $provider = new class implements SupplierProvider
        {
            /** @var list<string> */
            public array $requests = [];

            public function issue(OrderItem $item, string $traceId, int $attempt): array
            {
                $this->requests[] = $traceId;
                if (count($this->requests) === 1) {
                    throw new RuntimeException('simulated worker interruption');
                }

                return ['result' => 'success', 'error' => null];
            }

            public function queryIssuance(OrderItem $item, string $traceId): ?array
            {
                return null;
            }

            public function cancelIssuance(OrderItem $item, string $traceId): bool
            {
                return true;
            }
        };
        $issuance = new Issuance($provider);
        try {
            $issuance->process($event->id);
            self::fail('The injected interruption should escape to the queue worker.');
        } catch (RuntimeException $error) {
            self::assertSame('simulated worker interruption', $error->getMessage());
        }

        $issuance->process($event->id);
        self::assertSame([$event->trace_id], $provider->requests);
        self::assertSame(0, $order->tickets()->count());

        $this->travel(121)->seconds();
        $issuance->process($event->id);
        $issuance->process($event->id);
        self::assertSame([$event->trace_id, $event->trace_id], $provider->requests);
        self::assertSame(1, $order->tickets()->count());
    }

    public function test_async_supplier_callback_is_signed_scoped_and_idempotent(): void
    {
        $product = Product::factory()->stocked()->create();
        $product->supplier->update(['adapter' => 'webhook']);
        $order = $this->place($product);
        $this->pay($order);
        $this->issue($order);
        $event = $order->issuance()->firstOrFail();
        self::assertSame('awaiting_callback', $event->status);
        self::assertSame(0, $order->tickets()->count());

        $this->travel(6)->seconds();
        $gateway = app(MockSupplier::class);
        $body = json_encode([
            'event_id' => (string) Str::uuid(),
            'request' => $event->trace_id,
            'status' => 'succeeded',
            'sent_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR);
        $send = fn (int $supplier, string $signature, string $payload = '') => $this->call(
            'POST', '/webhooks/mock-suppliers/'.$supplier, [], [], [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TRAVELOPS_SIGNATURE' => $signature],
            $payload === '' ? $body : $payload,
        );
        $supplier = $product->supplier_id;
        $other = Product::factory()->stocked()->create()->supplier_id;
        $send($supplier, 'fake')->assertUnauthorized();
        $send($other, $gateway->signature($other, $body))->assertUnprocessable();

        $signature = $gateway->signature($supplier, $body);
        for ($delivery = 0; $delivery < 3; $delivery++) {
            $send($supplier, $signature)->assertOk()->assertJsonPath('outcome', 'succeeded');
        }
        $late = json_encode([
            'event_id' => (string) Str::uuid(),
            'request' => $event->trace_id,
            'status' => 'pending',
            'sent_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR);
        $send($supplier, $gateway->signature($supplier, $late), $late)->assertOk()->assertJsonPath('outcome', 'ignored_final');
        $this->travel(301)->seconds();
        $send($supplier, $signature)->assertUnprocessable();
        self::assertSame('fulfilled', $order->refresh()->status);
        self::assertSame(1, $order->tickets()->count());
        $this->assertDatabaseCount('supplier_statements', 1);
    }

    public function test_lost_supplier_reply_is_reconciled_from_the_sandbox_ledger(): void
    {
        $product = Product::factory()->stocked()->create();
        $product->supplier->update(['scenario' => 'lost_reply']);
        $order = $this->place($product);
        $this->pay($order);
        $this->issue($order);
        self::assertSame('retryable_failed', $order->issuance()->firstOrFail()->status);
        self::assertSame(0, $order->tickets()->count());
        $this->assertDatabaseCount('supplier_statements', 1);

        $this->travel(45)->seconds();
        $this->issue($order);
        self::assertSame('fulfilled', $order->refresh()->status);
        self::assertSame(1, $order->tickets()->count());
        $this->assertDatabaseCount('supplier_statements', 1);
    }
}
