<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Supplier> */
final class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return ['name' => '供應商 '.Str::uuid(), 'scenario' => 'success'];
    }
}
