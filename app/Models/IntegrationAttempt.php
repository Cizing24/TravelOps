<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IntegrationAttempt extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<OutboxEvent, $this> */
    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class);
    }
}
