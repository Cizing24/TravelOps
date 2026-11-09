<?php

namespace App\Domain;

use Carbon\CarbonImmutable;

final class TicketWindow
{
    public static function expiresAt(CarbonImmutable $firstUse, int $days): CarbonImmutable
    {
        return $firstUse->utc()->addDays($days);
    }

    public static function expired(?CarbonImmutable $expiry, CarbonImmutable $now): bool
    {
        return $expiry !== null && $now->greaterThanOrEqualTo($expiry);
    }
}
