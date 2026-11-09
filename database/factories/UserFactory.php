<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['name' => '測試人員', 'email' => Str::uuid().'@travelops.test', 'password' => Str::random(24), 'role' => 'customer'];
    }
}
