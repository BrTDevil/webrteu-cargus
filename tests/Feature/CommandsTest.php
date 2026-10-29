<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\Cargus\CargusClient;
use WeBRTeu\Cargus\CargusServiceProvider;
use WeBRTeu\Cargus\Facades\Cargus;
use WeBRTeu\Cargus\Models\CargusLocality;
use WeBRTeu\Cargus\Models\CargusPudoPoint;
use WeBRTeu\Shipping\Facades\Shipping;

it('syncs localities', function (): void {
    fakeCargus([
        CARGUS.'/Counties*' => Http::response(fixtureFile('counties.json')),
        CARGUS.'/Localities*' => Http::response(fixtureFile('localities.json')),
    ]);

    $this->artisan('cargus:sync-locations')->expectsOutput('Localități sincronizate: 2.');

    expect(CargusLocality::query()->where('locality_id', 501)->sole())
        ->name_key->toBe('rasnov')
        ->county_id->toBe(8)
        ->getTable()->toBe('cargus_localities');
});

it('handles empty nomenclature responses', function (): void {
    fakeCargus([CARGUS.'/Counties*' => Http::response('null')]);
    $this->artisan('cargus:sync-locations')->expectsOutput('Localități sincronizate: 0.');

    fakeCargus([CARGUS.'/Counties*' => Http::response(fixtureFile('counties.json')), CARGUS.'/Localities*' => Http::response('null')]);
    $this->artisan('cargus:sync-locations')->expectsOutput('Localități sincronizate: 0.');
});

it('syncs Ship & Go points and filters them', function (): void {
    CargusPudoPoint::query()->create(['pudo_id' => 999, 'name' => 'x', 'county' => 'x', 'city' => 'x', 'address' => 'x']);
    fakeCargus([CARGUS.'/PudoPoints' => Http::response(fixtureFile('pudo-points.json'))]);

    $this->artisan('cargus:sync-pudo')->expectsOutput('Puncte Ship & Go sincronizate: 2, dezactivate: 1.');

    $points = Shipping::carrier('cargus')->pickupPoints('Brașov', 'Brașov');

    expect($points)->toHaveCount(1)
        ->and($points[0]->supportsCod)->toBeTrue()
        ->and($points[0]->lat)->toBe(45.64)
        ->and(CargusPudoPoint::query()->where('pudo_id', 301)->sole()->getTable())->toBe('cargus_pudo_points');
});

it('reads Ship & Go points from the API until synced', function (): void {
    fakeCargus([CARGUS.'/PudoPoints' => Http::response(fixtureFile('pudo-points.json'))]);

    $points = Shipping::carrier('cargus')->pickupPoints(city: 'Râșnov');

    expect($points)->toHaveCount(1)
        ->and($points[0]->supportsCod)->toBeFalse()
        ->and($points[0]->postalCode)->toBeNull();
});

it('does not deactivate anything on an empty response', function (): void {
    fakeCargus([CARGUS.'/PudoPoints' => Http::response([])]);

    $this->artisan('cargus:sync-pudo')->expectsOutput('Puncte Ship & Go sincronizate: 0, dezactivate: 0.');
});

it('prints the pickup locations', function (): void {
    fakeCargus([CARGUS.'/PickupLocations' => Http::response(fixtureFile('pickup-locations.json'))]);

    $this->artisan('cargus:pickup-locations')->expectsTable(['LocationId', 'Nume', 'Localitate', 'Adresă'], [
        ['201', 'Depozit Brașov', 'Brasov', 'Str. Fabricii 5'],
        ['202', '', '', ''],
    ]);
});

it('registers the carrier', function (): void {
    expect(Shipping::available())->toContain('cargus')
        ->and(Cargus::client())->toBeInstanceOf(CargusClient::class)
        ->and(ServiceProvider::pathsToPublish(CargusServiceProvider::class, 'cargus-migrations'))->toHaveCount(1);
});
