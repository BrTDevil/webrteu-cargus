<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Cargus\Facades\Cargus;
use WeBRTeu\Shipping\Data\PickupRequestData;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Facades\Shipping;

it('traces several AWBs in one call', function (): void {
    fakeCargus([CARGUS.'/AwbTrace*' => Http::response(fixtureFile('awb-trace.json'))]);

    $results = Shipping::carrier('cargus')->trackMany(['1234567890', '1234567891']);

    expect($results->keys()->map(strval(...))->all())->toBe(['1234567890', '1234567891'])
        ->and($results['1234567890']->status)->toBe(ShipmentStatus::Delivered)
        ->and($results['1234567890']->events[0]->location)->toBe('Brasov')
        ->and($results['1234567891']->status)->toBe(ShipmentStatus::InTransit)
        ->and($results['1234567891']->events[0]->occurredAt)->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'barCode=["1234567890","1234567891"]'));
});

it('reports an AWB missing from the trace', function (): void {
    fakeCargus([CARGUS.'/AwbTrace*' => Http::response([])]);

    expect(Shipping::carrier('cargus')->track('999')->status)->toBe(ShipmentStatus::Unknown);
});

it('orders a pickup', function (): void {
    fakeCargus([CARGUS.'/Orders*' => Http::sequence()->push('555')->push(['x' => 1])]);

    expect(Cargus::orderPickup(new PickupRequestData(CarbonImmutable::parse('2026-01-05'), '10:00', '16:00', 2, 3.2)))->toBe('555')
        ->and(Cargus::orderPickup(new PickupRequestData(CarbonImmutable::parse('2026-01-05'), '10:00', '16:00', 2, 3.2)))->toBe('');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'action=1')
        && $request['PickupStartDate'] === '2026-01-05T10:00:00'
        && $request['TotalWeight'] === 4);
});

it('lists the static services', function (): void {
    $services = Shipping::carrier('cargus')->services();

    expect($services->pluck('code')->all())->toBe(['1', '34', '38'])
        ->and($services->firstWhere('code', '38')?->pickupPoint)->toBeTrue()
        ->and($services->firstWhere('code', '1')?->pickupPoint)->toBeFalse();
});
