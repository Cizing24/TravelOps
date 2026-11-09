<?php

namespace Tests\Integration;

use App\Application\PaymentWebhook;
use App\Infrastructure\MockGateway;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Redemption;
use App\Models\Store;
use App\Models\User;
use App\Models\Venue;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\CreatesOrders;
use Tests\ProcessEnvironment;
use Tests\TestCase;

final class MySqlConcurrencyTest extends TestCase
{
    use CreatesOrders;
    use DatabaseMigrations;
    use ProcessEnvironment;

    public function test_independent_buyers_race_for_the_last_unit(): void
    {
        $product = Product::factory()->stocked(1)->create();
        $buyers = User::factory()->count(2)->create();
        $replacement = Venue::factory()->create();

        $result = $this->race('products', $product->id, [
            ['checkout', $product->id, $buyers[0]->id],
            ['checkout', $product->id, $buyers[1]->id],
        ], function () use ($product, $replacement): void {
            $product->update(['price' => 41000]);
            $product->venues()->sync([$replacement->id]);
        });

        $this->assertEqualsCanonicalizing(['pending_payment', 'sold_out'], $result);
        $snapshot = OrderItem::firstOrFail();
        self::assertSame(41000, $snapshot->unit_price);
        self::assertSame([$replacement->id], array_column($snapshot->venues, 'id'));
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 0]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
    }

    public function test_two_merchant_sessions_cannot_redeem_the_same_entitlement(): void
    {
        $product = Product::factory()->stocked()->create();
        $venue = $product->venues()->firstOrFail();
        $order = $this->place($product);
        $this->pay($order);
        $this->issue($order);
        $ticket = $order->tickets()->firstOrFail();
        $staff = User::factory()->count(2)->create(['role' => 'merchant']);
        foreach ($staff as $actor) {
            $actor->venues()->attach($venue->id);
        }
        $result = $this->race('orders', $order->id, [
            ['redeem', $order->id, $staff[0]->id, $venue->id, $ticket->code],
            ['redeem', $order->id, $staff[1]->id, $venue->id, $ticket->code],
        ]);

        $this->assertEqualsCanonicalizing(['redeemed', 'already_redeemed'], $result);
        $this->assertDatabaseCount('redemptions', 1);
        $this->assertDatabaseHas('ticket_entitlements', ['ticket_id' => $ticket->id, 'status' => 'redeemed']);
        $record = Redemption::firstOrFail();
        $attributes = $record->only(['ticket_entitlement_id', 'user_id', 'venue_id', 'redeemed_at']);
        try {
            Redemption::create($attributes + ['trace_id' => (string) Str::uuid()]);
            self::fail('MySQL should enforce the unique entitlement index');
        } catch (QueryException $error) {
            self::assertSame('23000', $error->errorInfo[0]);
        }
        $this->assertDatabaseCount('redemptions', 1);
    }

    public function test_online_and_pos_contend_for_the_same_last_unit(): void
    {
        $product = Product::factory()->stocked(1)->create();
        $buyer = User::factory()->create();
        $clerk = User::factory()->create(['role' => 'pos_clerk']);
        $store = Store::create(['name' => '灣岸測試門市']);
        $clerk->stores()->attach($store);

        $result = $this->race('products', $product->id, [
            ['checkout', $product->id, $buyer->id],
            ['pos', $product->id, $clerk->id, $store->id],
        ]);

        self::assertContains('sold_out', $result);
        self::assertCount(1, array_intersect($result, ['pending_payment', 'paid']));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 0]);
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'kind' => 'reservation', 'delta' => -1]);
    }

    public function test_payment_and_expiry_contend_without_releasing_paid_stock(): void
    {
        $product = Product::factory()->stocked(2)->create();
        $onTime = $this->place($product);
        $deadline = $onTime->pay_by->toIso8601String();
        $result = $this->race('orders', $onTime->id, [
            ['pay', $onTime->id, $onTime->pay_by->subSecond()->toIso8601String()],
            ['expire', $onTime->id, $deadline],
        ]);
        $onTime->refresh();
        if ($onTime->status === 'paid') {
            self::assertEqualsCanonicalizing(['paid', 'unchanged'], $result);
            self::assertSame('sold', $onTime->reservation()->firstOrFail()->status);
            self::assertSame('succeeded', $onTime->payment()->firstOrFail()->status);
            self::assertSame(1, $onTime->issuance()->count());
        } else {
            self::assertEqualsCanonicalizing(['ignored_final', 'expired'], $result);
            self::assertSame('expired', $onTime->status);
            self::assertSame('released', $onTime->reservation()->firstOrFail()->status);
            self::assertSame('failed', $onTime->payment()->firstOrFail()->status);
            self::assertSame(0, $onTime->issuance()->count());
        }

        $late = $this->place($product);
        $cutoff = $late->pay_by->toIso8601String();
        $this->race('orders', $late->id, [['pay', $late->id, $cutoff], ['expire', $late->id, $cutoff]]);
        $this->assertSame('expired', $late->refresh()->status);
        $this->assertSame('released', $late->reservation()->firstOrFail()->status);
        $this->assertSame('failed', $late->payment()->firstOrFail()->status);
        $this->assertSame(0, $late->issuance()->count());
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => $onTime->status === 'paid' ? 1 : 2]);
    }

    public function test_database_failure_rolls_back_a_partially_applied_payment_and_the_same_event_can_retry(): void
    {
        $order = $this->place();
        $signed = app(MockGateway::class)->event($order, 'succeeded');
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT test_abort_payment CHECK (action <> 'payment.paid')");
        try {
            app(PaymentWebhook::class)->receive($signed['body'], $signed['signature']);
            self::fail('The audit insert must fail');
        } catch (QueryException $error) {
            self::assertSame(3819, $error->errorInfo[1]);
        } finally {
            DB::statement('ALTER TABLE audit_logs DROP CHECK test_abort_payment');
        }
        $this->assertSame('pending_payment', $order->refresh()->status);
        $this->assertSame('held', $order->reservation()->firstOrFail()->status);
        $this->assertSame('initiated', $order->payment()->firstOrFail()->status);
        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertDatabaseCount('outbox_events', 0);
        app(PaymentWebhook::class)->receive($signed['body'], $signed['signature']);
        $this->assertSame('paid', $order->refresh()->status);
    }

    /** @param list<list<int|string>> $operations
     * @return list<string>
     */
    private function race(string $lockedTable, int $rowId, array $operations, ?Closure $beforeRelease = null): array
    {
        $workers = [];
        DB::beginTransaction();
        try {
            DB::table($lockedTable)->where('id', $rowId)->lockForUpdate()->first();
            foreach ($operations as $operation) {
                $worker = new Process(
                    [PHP_BINARY, base_path('tests/Support/race-worker.php'), ...array_map('strval', $operation)],
                    base_path(), $this->processEnvironment(), timeout: 30,
                );
                $worker->start();
                $workers[] = $worker;
            }

            $parent = (int) DB::scalar('SELECT CONNECTION_ID()');
            $until = microtime(true) + 12;
            do {
                $waiting = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(
                    fn ($connection) => $connection->db === 'travelops_test'
                        && (int) $connection->Id !== $parent
                        && str_contains(strtolower((string) $connection->Info), 'from `'.$lockedTable.'`')
                        && str_contains(strtolower((string) $connection->Info), 'for update'),
                )->count();
                if ($waiting >= count($operations)) {
                    break;
                }
                usleep(40_000);
            } while (microtime(true) < $until && collect($workers)->every(fn (Process $worker) => $worker->isRunning()));
            $this->assertSame(count($operations), $waiting, 'Each worker must contend on the same MySQL row lock.');
            $beforeRelease?->__invoke();
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->wait();
            }
        }

        return array_map(function (Process $worker): string {
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());

            return trim($worker->getOutput());
        }, $workers);
    }
}
