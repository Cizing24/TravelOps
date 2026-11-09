<?php

namespace Tests\Unit;

use App\Domain\Money;
use App\Domain\OrderState;
use App\Domain\TicketWindow;
use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class DomainTest extends TestCase
{
    public function test_twd_minor_units_stay_integer(): void
    {
        self::assertSame(3003, Money::total(1001, 3));
        self::assertSame('NT$ 30.03', Money::twd(3003));
        self::assertSame('NT$ 1,200', Money::twd(120000));
    }

    public function test_only_captured_orders_can_enter_fulfillment_and_refund_review(): void
    {
        self::assertTrue(OrderState::awaitingPayment('pending_payment'));
        self::assertFalse(OrderState::awaitingPayment('paid'));
        self::assertTrue(OrderState::canIssue('fulfilling'));
        self::assertFalse(OrderState::canIssue('fulfilled'));
        self::assertTrue(OrderState::captured('fulfilled'));
        self::assertFalse(OrderState::captured('refunded'));
        self::assertNull(OrderState::refundRejection(false, true));
        self::assertNotNull(OrderState::refundRejection(true, true));
        self::assertNotNull(OrderState::refundRejection(false, false));
    }

    public function test_customer_policy_checks_both_role_and_owner(): void
    {
        $policy = new OrderPolicy;
        $owner = (new User)->forceFill(['id' => 12, 'role' => 'customer']);
        $other = (new User)->forceFill(['id' => 13, 'role' => 'customer']);
        $merchant = (new User)->forceFill(['id' => 12, 'role' => 'merchant']);
        $order = (new Order)->forceFill(['user_id' => 12]);
        self::assertTrue($policy->view($owner, $order));
        self::assertFalse($policy->view($other, $order));
        self::assertFalse($policy->view($merchant, $order));
        self::assertFalse($policy->create($merchant));
    }

    public function test_ticket_window_at_taipei_midnight_and_exact_expiry(): void
    {
        $first = CarbonImmutable::parse('2026-11-10 00:00:00', 'Asia/Taipei');
        $expiry = TicketWindow::expiresAt($first, 14);
        self::assertSame('2026-11-23T16:00:00+00:00', $expiry->toIso8601String());
        self::assertSame('2026-11-24 00:00:00', $expiry->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s'));
        self::assertFalse(TicketWindow::expired($expiry, $expiry->subSecond()));
        self::assertTrue(TicketWindow::expired($expiry, $expiry));
        self::assertFalse(TicketWindow::expired(null, $expiry));
    }
}
