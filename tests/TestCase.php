<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = DB::connection();
        if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'travelops_test'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'mysql'], true)) {
            throw new RuntimeException('Destructive tests require the dedicated local MySQL travelops_test database.');
        }

        return $app;
    }
}
