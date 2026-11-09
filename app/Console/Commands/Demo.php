<?php

namespace App\Console\Commands;

use App\Application\Checkout;
use App\Application\Issuance;
use App\Application\PaymentWebhook;
use App\Application\RefundOrder;
use App\Infrastructure\MockGateway;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Demo extends Command
{
    protected $signature = 'travelops:demo {--reset-passwords : Generate new passwords for existing demo accounts}';

    protected $description = 'Create the synthetic TravelOps catalog, demo users and example transactions';

    public function handle(MockGateway $gateway): int
    {
        if (! app()->environment('local') || ! $gateway->enabled() || DB::connection()->getDatabaseName() !== 'travelops_demo') {
            $this->error('此指令僅可用於已設定金鑰及模擬回呼密鑰的本地 travelops_demo。');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class]);
        $accounts = [
            'customer' => ['旅客', 'customer', null],
            'operator' => ['營運', 'operator', null],
            'admin' => ['管理員', 'admin', null],
            'merchant-a' => ['場館 A', 'merchant', DatabaseSeeder::VENUES[0]],
            'merchant-b' => ['場館 B', 'merchant', DatabaseSeeder::VENUES[1]],
            'merchant-c' => ['場館 C', 'merchant', DatabaseSeeder::VENUES[2]],
            'merchant-d' => ['場館 D', 'merchant', DatabaseSeeder::VENUES[3]],
            'pos-a' => ['門市 A', 'pos_clerk', null],
            'pos-supervisor-a' => ['門市 A 主管', 'pos_supervisor', null],
            'pos-b' => ['門市 B', 'pos_clerk', null],
        ];
        $users = [];
        $credentials = [];
        foreach ($accounts as $alias => [$name, $role, $venue]) {
            $email = $alias.'@travelops.test';
            $user = User::firstOrNew(['email' => $email]);
            if (! $user->exists || $this->option('reset-passwords')) {
                $password = Str::random(24);
                $user->fill(['name' => $name, 'role' => $role, 'password' => $password])->save();
                if ($venue !== null) {
                    $user->venues()->sync([Venue::where('name', $venue)->firstOrFail()->id]);
                }
                $credentials[] = [$email, $password];
            }
            if ($user->staffsPos()) {
                $store = Store::firstOrCreate(['name' => $alias === 'pos-b' ? '港城展示門市' : '灣岸展示門市']);
                $user->stores()->syncWithoutDetaching([$store->id]);
            }
            $users[$alias] = $user;
        }
        if ($credentials !== []) {
            $this->table(['Email', '本次產生的密碼'], $credentials);
        } else {
            $this->info('帳號已建立。可使用 --reset-passwords 重新產生密碼。');
        }

        $this->example($users['customer'], $users['operator'], 1, 'coast-four', 'issued');
        $this->example($users['customer'], $users['operator'], 2, 'bay-aquarium', 'payment_failed');
        $this->example($users['customer'], $users['operator'], 3, 'bay-aquarium', 'refunded');
        $this->example($users['customer'], $users['operator'], 4, 'cape-view', 'issuance_failed');
        $this->info('展示情境已備妥。');

        return self::SUCCESS;
    }

    private function example(User $customer, User $operator, int $index, string $slug, string $outcome): void
    {
        $key = sprintf('0dba0000-5070-4000-8000-%012d', $index);
        $product = Product::where('slug', $slug)->firstOrFail();
        $order = app(Checkout::class)->place($customer, $product->id, 1, $key);
        if ($order->status === 'pending_payment' && $order->payment?->status === 'initiated') {
            $status = $outcome === 'payment_failed' ? 'failed' : 'succeeded';
            $event = app(MockGateway::class)->event($order, $status);
            app(PaymentWebhook::class)->receive($event['body'], $event['signature']);
        }
        $issuance = $order->issuance()->first();
        if ($issuance !== null) {
            app(Issuance::class)->process($issuance->id);
        }
        if ($outcome === 'refunded' && $order->refresh()->status === 'fulfilled') {
            $refunds = app(RefundOrder::class);
            $refunds->review($operator, $refunds->request($customer, $order), true);
        }
    }
}
