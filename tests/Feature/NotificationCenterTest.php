<?php

namespace Tests\Feature;

use App\Application\NotificationCenter;
use App\Application\RefundOrder;
use App\Models\NotificationDelivery;
use App\Models\NotificationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesOrders;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use CreatesOrders;
    use RefreshDatabase;

    public function test_transaction_notifications_are_private_idempotent_and_respect_preferences(): void
    {
        $customer = User::factory()->create();
        $operator = User::factory()->create(['role' => 'operator']);
        $this->actingAs($customer)->post('/my/notifications/preferences', ['notification_email' => '1'])->assertRedirect();
        $order = $this->place(customer: $customer);
        $this->pay($order);
        $this->pay($order);
        $this->issue($order);
        $this->issue($order);
        $refunds = app(RefundOrder::class);
        $refund = $refunds->review($operator, $refunds->request($customer, $order), true);
        $refunds->process($refund->id);
        $refunds->process($refund->id);
        $this->assertDatabaseCount('notification_messages', 3);
        $this->assertDatabaseCount('notification_deliveries', 6);
        $this->get('/my/notifications')->assertOk()->assertSee('付款已確認')->assertSee('票券已開立')->assertSee('退款已確認');
        $message = NotificationMessage::where('template', 'paid')->firstOrFail();
        $this->post('/my/notifications/'.$message->id.'/read')->assertRedirect();
        self::assertNotNull($message->refresh()->read_at);

        $center = app(NotificationCenter::class);
        $email = NotificationDelivery::where('channel', 'email')->firstOrFail();
        $center->deliver($email->id);
        self::assertSame('sent', $email->refresh()->status);
        $this->assertDatabaseHas('notification_deliveries', ['channel' => 'line', 'status' => 'suppressed']);
        $this->actingAs(User::factory()->create())->get('/my/notifications')->assertOk()->assertDontSee('付款已確認');
        $this->post('/my/notifications/'.$message->id.'/read')->assertNotFound();
    }

    public function test_expiry_reminders_deduplicate_and_retry_the_simulated_line_failure(): void
    {
        $customer = User::factory()->create();
        $customer->forceFill(['notification_line' => true, 'remind_expiry' => true])->save();
        $order = $this->place(customer: $customer, quantity: 2);
        $this->pay($order);
        $this->issue($order);
        $ticket = $order->tickets()->firstOrFail();
        $ticket->update(['first_used_at' => now()->subDays(13), 'expires_at' => now('Asia/Taipei')->addDay()->utc()]);
        $center = app(NotificationCenter::class);
        $center->remind();
        $center->remind();
        $message = NotificationMessage::where('template', 'expiry_1')->sole();
        self::assertSame($ticket->id, $message->ticket_id);
        $line = NotificationDelivery::where('notification_message_id', $message->id)->where('channel', 'line')->sole();
        $center->deliver($line->id);
        self::assertSame('failed', $line->refresh()->status);
        self::assertSame('mock_channel_unavailable', $line->last_error);
        $this->travel(20)->seconds();
        $center->deliver($line->id);
        $center->deliver($line->id);
        self::assertSame('sent', $line->refresh()->status);
        self::assertSame(2, $line->attempts);
    }
}
