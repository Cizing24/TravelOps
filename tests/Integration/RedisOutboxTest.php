<?php

namespace Tests\Integration;

use App\Application\Issuance;
use App\Infrastructure\MockGateway;
use App\Jobs\DeliverMockPayment;
use App\Jobs\IssueTickets;
use App\Models\Product;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\CreatesOrders;
use Tests\ProcessEnvironment;
use Tests\TestCase;

final class RedisOutboxTest extends TestCase
{
    use CreatesOrders;
    use DatabaseMigrations;
    use ProcessEnvironment;

    public function test_persistent_handoff_redis_worker_retry_and_lost_job_reconciliation(): void
    {
        self::assertSame('PONG', (string) Redis::connection()->ping());
        $queue = Queue::connection('redis');
        $queue->clear('travelops_test');
        $product = Product::factory()->stocked()->create();
        $product->supplier->update(['scenario' => 'timeout_once']);
        $order = $this->place($product);
        $this->pay($order);
        $event = $order->issuance()->firstOrFail();
        $issuance = app(Issuance::class);

        $real = Bus::getFacadeRoot();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('injected Redis dispatch outage'));
        self::assertSame(0, $issuance->relay());
        self::assertSame('pending', $event->refresh()->status);
        self::assertNull($event->queued_until);
        $this->app->instance(Dispatcher::class, $real);
        Bus::swap($real);

        self::assertSame(1, $issuance->relay());
        self::assertSame(0, $issuance->relay());
        $this->worker();
        self::assertSame('retryable_failed', $event->refresh()->status);
        self::assertSame(1, $event->attempts);
        self::assertStringContainsString('逾時', $event->last_error);
        self::assertSame(0, $issuance->relay());
        $event->update(['available_at' => now()->subSecond()]);
        self::assertSame(1, $issuance->relay());
        self::assertGreaterThanOrEqual(1, $queue->clear('travelops_test'));
        $event->update(['queued_until' => now()->subSecond()]);
        self::assertSame(1, $issuance->relay());
        $this->worker();
        self::assertSame('succeeded', $event->refresh()->status);
        self::assertSame(2, $event->attempts);
        self::assertSame(2, $event->history()->count());
        Bus::dispatch(new IssueTickets($event->id));
        $this->worker();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_delayed_mock_payment_is_consumed_from_redis(): void
    {
        Queue::connection('redis')->clear('travelops_test');
        $order = $this->place();
        $event = app(MockGateway::class)->event($order, 'succeeded');
        DeliverMockPayment::dispatch($event['body'], $event['signature'])->delay(now()->addSecond());
        self::assertSame('pending_payment', $order->refresh()->status);
        $this->worker();
        if ($order->refresh()->status === 'pending_payment') {
            usleep(1_100_000);
            $this->worker();
        }
        self::assertSame('paid', $order->refresh()->status);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    public function test_async_supplier_confirms_issuance_from_the_redis_worker(): void
    {
        Queue::connection('redis')->clear('travelops_test');
        $product = Product::factory()->stocked()->create();
        $product->supplier->update(['adapter' => 'webhook']);
        $order = $this->place($product);
        $this->pay($order);
        self::assertSame(1, app(Issuance::class)->relay());
        $this->worker();
        self::assertSame('awaiting_callback', $order->issuance()->firstOrFail()->status);
        $this->assertDatabaseCount('tickets', 0);

        $deadline = microtime(true) + 15;
        do {
            usleep(300_000);
            $this->worker();
        } while ($order->refresh()->status !== 'fulfilled' && microtime(true) < $deadline);

        self::assertSame('fulfilled', $order->status);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('webhook_events', ['provider' => 'supplier', 'order_id' => $order->id, 'outcome' => 'succeeded']);
    }

    private function worker(): void
    {
        $worker = new Process(
            [PHP_BINARY, 'artisan', 'queue:work', 'redis', '--queue=travelops_test', '--once', '--sleep=0'],
            base_path(), $this->processEnvironment(), timeout: 20,
        );
        $worker->mustRun();
        self::assertStringNotContainsString('FAIL', $worker->getOutput());
    }
}
