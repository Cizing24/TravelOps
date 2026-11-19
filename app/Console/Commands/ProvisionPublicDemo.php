<?php

namespace App\Console\Commands;

use App\Application\Audit;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ProvisionPublicDemo extends Command
{
    protected $signature = 'travelops:provision-public-demo';

    protected $description = 'Provision a new isolated synthetic public demo without printing credentials';

    public function handle(): int
    {
        if (! app()->environment('staging') || ! config('travelops.public_demo') || config('app.debug')
            || ! str_starts_with((string) config('app.url'), 'https://')
            || DB::connection()->getDatabaseName() !== 'travelops_public_demo'
            || config('travelops.provision_confirm') !== 'CREATE_SYNTHETIC_DEMO') {
            $this->error('Requires an explicitly confirmed HTTPS staging public-demo database.');

            return self::FAILURE;
        }

        $passwords = [
            'customer' => (string) config('travelops.provision_passwords.customer'),
            'operator' => (string) config('travelops.provision_passwords.operator'),
            'merchant' => (string) config('travelops.provision_passwords.merchant'),
            'pos_clerk' => (string) config('travelops.provision_passwords.pos_clerk'),
        ];
        if (count(array_unique($passwords)) !== 4 || collect($passwords)->contains(fn (string $password): bool => strlen($password) < 24 || str_starts_with($password, 'REPLACE_'))) {
            $this->error('Provide distinct private passwords of at least 24 characters for every demo role.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($passwords): void {
            if (User::exists() || Product::exists()) {
                throw new \RuntimeException('Refusing to modify an existing dataset.');
            }
            $supplier = Supplier::create(['name' => '合成示範供應商', 'scenario' => 'success']);
            $venue = Venue::create(['name' => '合成展示場館', 'supplier_id' => $supplier->id]);
            $product = Product::create([
                'slug' => 'synthetic-entry', 'name' => '合成展示票券（不可入場）', 'price' => 100,
                'description' => '純模擬付款、開券與核銷，沒有真實消費或場館。',
                'supplier_id' => $supplier->id, 'valid_days' => 14, 'kind' => 'single', 'listed' => true,
            ]);
            $product->venues()->attach($venue->id);
            $product->inventory()->create(['available' => 100]);
            $store = Store::create(['name' => '合成展示門市']);
            foreach (['customer', 'operator', 'merchant', 'pos_clerk'] as $role) {
                // These credentials stay private; public visitors only browse the catalog.
                $user = User::create(['name' => '合成 '.$role, 'email' => $role.'@travelops.example.test', 'role' => $role, 'password' => $passwords[$role]]);
                if ($role === 'merchant') {
                    $user->venues()->attach($venue->id);
                }
                if ($role === 'pos_clerk') {
                    $user->stores()->attach($store->id);
                }
                Audit::record('public_demo_provisioned', $user->id, null, ['role' => $role, 'source' => 'synthetic-only']);
            }
        });
        $this->info('Synthetic public demo provisioned; credentials are never printed. No public admin account was created.');

        return self::SUCCESS;
    }
}
