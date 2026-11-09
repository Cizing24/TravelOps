<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['listed' => 'boolean'];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsToMany<Venue, $this> */
    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'product_entitlements');
    }

    /** @return HasOne<InventoryBalance, $this> */
    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryBalance::class);
    }
}
