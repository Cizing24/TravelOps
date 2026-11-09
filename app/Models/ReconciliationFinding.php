<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReconciliationFinding extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
