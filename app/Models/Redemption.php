<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Redemption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['redeemed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TicketEntitlement, $this> */
    public function ticketEntitlement(): BelongsTo
    {
        return $this->belongsTo(TicketEntitlement::class);
    }
}
