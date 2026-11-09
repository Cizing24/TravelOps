<?php

use App\Application\Checkout;
use App\Application\PaymentWebhook;
use App\Application\PointOfSale;
use App\Application\Redeem;
use App\Domain\RuleViolation;
use App\Infrastructure\MockGateway;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'travelops_test') {
    throw new RuntimeException('Race worker requires the isolated test database.');
}
[$script, $operation, $id] = $argv;

try {
    $result = match ($operation) {
        'checkout' => app(Checkout::class)->place(User::findOrFail($argv[3]), (int) $id, 1, (string) Str::uuid())->status,
        'pos' => app(PointOfSale::class)->sell(User::findOrFail($argv[3]), Store::findOrFail($argv[4]), (int) $id, 1, (string) Str::uuid(), 'cash')->status,
        'redeem' => app(Redeem::class)->ticket(User::findOrFail($argv[3]), (int) $argv[4], $argv[5])->id > 0 ? 'redeemed' : 'invalid',
        'expire' => (function () use ($id, $argv): string {
            CarbonImmutable::setTestNow($argv[3]);

            return app(Checkout::class)->expire((int) $id) ? 'expired' : 'unchanged';
        })(),
        'pay' => (function () use ($id, $argv): string {
            CarbonImmutable::setTestNow($argv[3]);
            $event = app(MockGateway::class)->event(Order::findOrFail($id), 'succeeded');

            return app(PaymentWebhook::class)->receive($event['body'], $event['signature']);
        })(),
        default => throw new InvalidArgumentException('Unknown race operation.'),
    };
} catch (RuleViolation $error) {
    $result = $error->reason;
}
echo $result;
