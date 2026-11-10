<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable|null $available_at */
final class NotificationDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['available_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<NotificationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(NotificationMessage::class, 'notification_message_id');
    }
}
