<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\Cargus\Commands\PickupLocationsCommand;
use WeBRTeu\Cargus\Commands\SyncLocationsCommand;
use WeBRTeu\Cargus\Commands\SyncPudoCommand;
use WeBRTeu\Cargus\Exceptions\CargusException;
use WeBRTeu\Shipping\ShippingManager;

class CargusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cargus.php', 'cargus');

        $this->app->singleton(CargusClient::class);
        $this->app->singleton(CargusCarrier::class);

        $this->callAfterResolving(ShippingManager::class, function (ShippingManager $shipping): void {
            $shipping->extend('cargus', fn (Container $app): CargusCarrier => $app->make(CargusCarrier::class));
        });
    }

    public function boot(): void
    {
        // În local: eroare clară imediat, nu un 401 obscur la primul request.
        $key = Config::get('cargus.subscription_key');

        if ($this->app->environment('local') && (! is_string($key) || $key === '')) {
            throw CargusException::missingSubscriptionKey();
        }

        $this->publishes([
            __DIR__.'/../config/cargus.php' => $this->app->configPath('cargus.php'),
        ], 'cargus-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_cargus_tables.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_cargus_tables.php'),
        ], 'cargus-migrations');

        $this->commands([SyncLocationsCommand::class, SyncPudoCommand::class, PickupLocationsCommand::class]);
    }
}
