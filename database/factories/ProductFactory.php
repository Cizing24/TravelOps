<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(), 'name' => '旅行票券', 'slug' => (string) Str::uuid(),
            'kind' => 'single', 'description' => '測試票券', 'price' => 32000,
            'valid_days' => 14, 'listed' => true,
        ];
    }

    public function stocked(int $available = 10, int $venues = 1): static
    {
        return $this->state(['kind' => $venues === 1 ? 'single' : 'bundle'])
            ->afterCreating(function (Product $product) use ($available, $venues): void {
                $product->inventory()->create(['available' => $available]);
                $product->venues()->attach(Venue::factory()->count($venues)->create(['supplier_id' => $product->supplier_id])->modelKeys());
            });
    }
}
