<?php

namespace App\Domain;

final class OrderState
{
    public static function awaitingPayment(string $status): bool
    {
        return $status === 'pending_payment';
    }

    public static function canIssue(string $status): bool
    {
        return in_array($status, ['paid', 'fulfilling'], true);
    }

    public static function captured(string $status): bool
    {
        return $status === 'fulfilled' || self::canIssue($status);
    }

    public static function refundRejection(bool $hasRedemptions, bool $approved): ?string
    {
        if ($hasRedemptions) {
            return '已有核銷紀錄，需交由人工處理。';
        }

        return $approved ? null : '營運人員拒絕退款。';
    }
}
