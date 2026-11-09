<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $queued_until
 */
final class OutboxEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['available_at' => 'immutable_datetime', 'queued_until' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<IntegrationAttempt, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(IntegrationAttempt::class);
    }
}
