<?php

namespace App\Application;

use App\Models\Refund;

interface RefundGateway
{
    public function submitRefund(Refund $refund): string;

    public function queryRefund(Refund $refund): ?string;
}
