<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Order extends Model
{
    protected $guarded = [];

    protected $attributes = ['source_channel' => 'online', 'payment_method' => 'mock_online'];

    protected function casts(): array
    {
        return ['pay_by' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasOne<OrderItem, $this> */
    public function item(): HasOne
    {
        return $this->hasOne(OrderItem::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return HasOne<InventoryReservation, $this> */
    public function reservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    /** @return HasOne<OutboxEvent, $this> */
    public function issuance(): HasOne
    {
        return $this->hasOne(OutboxEvent::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasOne<Refund, $this> */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /** @return HasMany<WebhookEvent, $this> */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }
}
