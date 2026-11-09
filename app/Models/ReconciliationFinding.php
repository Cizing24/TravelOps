<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ReconciliationFinding extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'reviewed_at' => 'immutable_datetime'];
    }
}
