<?php

namespace App\Domain;

use RuntimeException;

final class RuleViolation extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
