<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Tests;

use Illuminate\Database\Migrations\Migration;
use Orchestra\Testbench\TestCase as Orchestra;
use WeBRTeu\Cargus\CargusServiceProvider;
use WeBRTeu\Shipping\ShippingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ShippingServiceProvider::class, CargusServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cargus.subscription_key', env('CARGUS_SANDBOX_SUBSCRIPTION_KEY', 'sub-key'));
        $app['config']->set('cargus.username', env('CARGUS_SANDBOX_USERNAME', 'test-user'));
        $app['config']->set('cargus.password', env('CARGUS_SANDBOX_PASSWORD', 'secret-pass'));
        $app['config']->set('cargus.pickup_location_id', env('CARGUS_SANDBOX_LOCATION_ID', 201));
        $app['config']->set('cargus.service_id', 34);
        $app['config']->set('cargus.pudo_service_id', 38);
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach ([
            __DIR__.'/../vendor/webrteu/shipping/database/migrations/create_shipments_tables.php.stub',
            __DIR__.'/../database/migrations/create_cargus_tables.php.stub',
        ] as $stub) {
            /** @var Migration $migration */
            $migration = require $stub;
            $migration->up();
        }
    }
}
