<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Cargus\CargusServiceProvider;
use WeBRTeu\Cargus\Exceptions\CargusException;
use WeBRTeu\Shipping\Facades\Shipping;

it('fails clearly before any request when the key is missing', function (): void {
    Config::set('cargus.subscription_key');
    Http::fake();

    expect(fn () => Shipping::carrier('cargus')->track('1'))->toThrow(CargusException::class, 'CARGUS_SUBSCRIPTION_KEY');
    Http::assertNothingSent();
});

it('fails at boot in local when the key is missing', function (?string $key): void {
    Config::set('cargus.subscription_key', $key);
    $this->app['env'] = 'local';

    (new CargusServiceProvider($this->app))->boot();
})->with([null, ''])->throws(CargusException::class, 'CARGUS_SUBSCRIPTION_KEY');

it('boots fine outside local without a key', function (): void {
    Config::set('cargus.subscription_key');

    (new CargusServiceProvider($this->app))->boot();

    expect(true)->toBeTrue();
});
