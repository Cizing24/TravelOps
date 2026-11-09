<?php

namespace App\Models;

use App\Domain\TicketWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $first_used_at
 * @property CarbonImmutable|null $expires_at
 */
final class Ticket extends Model
{
    protected $guarded = [];

    protected $hidden = ['code', 'code_hash'];

    protected function casts(): array
    {
        return ['code' => 'encrypted', 'first_used_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    public function isExpired(): bool
    {
        return TicketWindow::expired($this->expires_at, now()->toImmutable());
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<TicketEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(TicketEntitlement::class);
    }
}
