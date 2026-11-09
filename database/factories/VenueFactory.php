<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Venue> */
final class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return ['name' => '場館 '.Str::uuid(), 'supplier_id' => Supplier::factory()];
    }
}
