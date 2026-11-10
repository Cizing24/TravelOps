<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable $ready_at */
final class RefundStatement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['ready_at' => 'immutable_datetime'];
    }
}
