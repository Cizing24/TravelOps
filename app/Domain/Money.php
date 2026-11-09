<?php

namespace App\Domain;

final class Money
{
    public static function total(int $unitPrice, int $quantity): int
    {
        return $unitPrice * $quantity;
    }

    public static function twd(int $amount): string
    {
        return 'NT$ '.number_format(intdiv($amount, 100)).($amount % 100 ? sprintf('.%02d', $amount % 100) : '');
    }
}
