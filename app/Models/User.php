<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'notification_email' => 'boolean', 'notification_line' => 'boolean', 'remind_expiry' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->operates();
    }

    public function operates(): bool
    {
        return in_array($this->role, ['admin', 'operator'], true);
    }

    public function administers(): bool
    {
        return $this->role === 'admin';
    }

    public function staffsPos(): bool
    {
        return in_array($this->role, ['pos_clerk', 'pos_supervisor'], true);
    }

    public function staffsStore(Store $store): bool
    {
        return $this->staffsPos() && $this->stores()->whereKey($store->id)->exists();
    }

    /** @return BelongsToMany<Store, $this> */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_users');
    }

    /** @return BelongsToMany<Venue, $this> */
    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'merchant_users')->withTimestamps();
    }
}
