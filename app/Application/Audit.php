<?php

namespace App\Application;

use App\Models\AuditLog;
use Illuminate\Support\Str;

final class Audit
{
    /** @param array<string, mixed> $details */
    public static function record(string $action, ?int $actor, ?int $order, array $details = [], ?string $trace = null): void
    {
        AuditLog::create([
            'action' => $action,
            'user_id' => $actor,
            'order_id' => $order,
            'trace_id' => $trace ?? (string) Str::uuid(),
            'details' => $details,
        ]);
    }
}
