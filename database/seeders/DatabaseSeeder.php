<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    public const VENUES = ['潮線水族館', '港城設計館', '岬角觀景台', '風土工藝館'];

    public function run(): void
    {
        if (! app()->environment('local') || ! config('travelops.demo') || DB::connection()->getDatabaseName() !== 'travelops_demo') {
            throw new RuntimeException('Demo seed requires the dedicated local travelops_demo database.');
        }

        DB::transaction(function (): void {
            $normal = Supplier::firstOrCreate(['name' => '灣岸票務'], ['scenario' => 'success']);
            $temporary = Supplier::firstOrCreate(['name' => '潮汐合作社'], ['scenario' => 'timeout_once']);
            $permanent = Supplier::firstOrCreate(['name' => '港城展務'], ['scenario' => 'permanent']);
            $async = Supplier::firstOrCreate(['name' => '島岸合作票務'], ['scenario' => 'success', 'adapter' => 'webhook']);
            $venues = collect(self::VENUES)->map(fn (string $name) => Venue::firstOrCreate(['name' => $name], ['supplier_id' => $normal->id]));
            $catalog = [
                ['bay-aquarium', '潮線水族館單館票', 32000, '沿著潮線認識近岸生態，含一張常設展入場券。', $normal->id, [$venues[0]->id]],
                ['harbor-design', '港城設計館單館票', 25000, '參觀港城設計與日常器物展。這項商品可用於測試開券重試。', $temporary->id, [$venues[1]->id]],
                ['cape-view', '岬角觀景台單館票', 18000, '從岬角眺望海岸。這項商品可用於測試開券異常補償。', $permanent->id, [$venues[2]->id]],
                ['coast-four', '灣岸四館聯票', 88000, '一次購買四個場館的入場權益，首次核銷後 14 天內可分次使用。', $normal->id, $venues->pluck('id')->all()],
                ['island-craft', '島岸工藝館單館票', 21000, '風土工藝館入場券，由模擬合作供應商非同步確認開券。', $async->id, [$venues[3]->id]],
            ];
            foreach ($catalog as [$slug, $name, $price, $description, $supplierId, $venueIds]) {
                $product = Product::firstOrCreate(['slug' => $slug], [
                    'name' => $name, 'price' => $price, 'description' => $description, 'supplier_id' => $supplierId,
                    'valid_days' => 14, 'kind' => count($venueIds) === 1 ? 'single' : 'bundle', 'listed' => true,
                ]);
                if ($product->wasRecentlyCreated) {
                    $product->venues()->attach($venueIds);
                    $product->inventory()->create(['available' => 40]);
                }
            }
        });
    }
}
