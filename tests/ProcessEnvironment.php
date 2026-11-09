<?php

namespace Tests;

trait ProcessEnvironment
{
    /** @return array<string, string|false> */
    private function processEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'), 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql', 'DB_URL' => false,
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => 'travelops_test',
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'redis', 'REDIS_CLIENT' => 'predis',
            'REDIS_HOST' => (string) config('database.redis.default.host'),
            'REDIS_PORT' => (string) config('database.redis.default.port'),
            'REDIS_PREFIX' => 'travelops_test_', 'REDIS_QUEUE' => 'travelops_test',
            'TRAVELOPS_DEMO' => 'true', 'TRAVELOPS_WEBHOOK_SECRET' => (string) config('travelops.webhook_secret'),
        ];
    }
}
