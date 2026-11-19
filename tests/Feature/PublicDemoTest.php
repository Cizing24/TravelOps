<?php

namespace Tests\Feature;

use App\Infrastructure\MockGateway;
use Tests\TestCase;

final class PublicDemoTest extends TestCase
{
    public function test_staging_mock_requires_an_explicit_isolated_https_demo(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        config(['travelops.demo' => true, 'travelops.public_demo' => true, 'app.debug' => false,
            'app.url' => 'https://demo.example.test', 'database.connections.mysql.database' => 'travelops_public_demo']);
        $gateway = new MockGateway;
        $this->assertTrue($gateway->enabled());
        config(['app.debug' => true]);
        $this->assertFalse($gateway->enabled());
        config(['app.debug' => false, 'database.connections.mysql.database' => 'important_data']);
        $this->assertFalse($gateway->enabled());
        config(['database.connections.mysql.database' => 'travelops_public_demo', 'app.url' => 'http://demo.example.test']);
        $this->assertFalse($gateway->enabled());
    }

    public function test_staging_never_exposes_external_mock_webhooks(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        $this->postJson('/webhooks/mock')->assertNotFound();
        $this->postJson('/webhooks/mock-suppliers/1')->assertNotFound();
        $this->postJson('/webhooks/mock-refunds')->assertNotFound();
    }
}
