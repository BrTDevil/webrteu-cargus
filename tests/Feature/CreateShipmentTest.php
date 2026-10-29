<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Cargus\Exceptions\CargusException;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Facades\Shipping;

it('creates an AWB', function (): void {
    fakeCargus([CARGUS.'/Awbs' => Http::response(fixtureFile('awb-created.json'))]);

    $awb = Shipping::carrier('cargus')->createShipment(shipmentData());

    expect($awb->awb)->toBe('1234567890')
        ->and($awb->parcelNumbers)->toBe(['1234567890']);
    Http::assertSent(fn (Request $request): bool => $request->url() === CARGUS.'/Awbs' && $request['CashRepayment'] === 149.7);
});

it('turns a 400 into a validation error', function (): void {
    fakeCargus([CARGUS.'/Awbs' => Http::response(fixtureFile('awb-validation-error.json'), 400)]);

    expect(fn () => Shipping::carrier('cargus')->createShipment(shipmentData()))
        ->toThrow(ShipmentValidationException::class, 'Recipient.PhoneNumber');
});

it('rethrows other API errors', function (): void {
    fakeCargus([CARGUS.'/Awbs' => Http::response('"Down"', 503)]);

    Shipping::carrier('cargus')->createShipment(shipmentData());
})->throws(CargusException::class, 'HTTP 503');

it('throws ShipmentStateUnknownException on timeout, without retry', function (): void {
    $attempts = 0;
    fakeCargus([CARGUS.'/Awbs' => function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('timeout');
    }]);

    expect(fn () => Shipping::carrier('cargus')->createShipment(shipmentData()))->toThrow(ShipmentStateUnknownException::class);
    expect($attempts)->toBe(1);
});

it('fails when no AWB number comes back', function (): void {
    fakeCargus([CARGUS.'/Awbs' => Http::response(['x' => 1])]);

    Shipping::carrier('cargus')->createShipment(shipmentData());
})->throws(CargusException::class, 'numărul AWB');

it('quotes with ShippingCalculation', function (): void {
    fakeCargus([CARGUS.'/ShippingCalculation' => Http::sequence()
        ->push(fixtureFile('shipping-calculation.json'))
        ->push(['Subtotal' => 10])
        ->push([])]);

    expect(Shipping::carrier('cargus')->quote(shipmentData()))->amount->toBe(1892)->serviceCode->toBe('34')
        ->and(Shipping::carrier('cargus')->quote(shipmentData())->amount)->toBe(1000)
        ->and(Shipping::carrier('cargus')->quote(shipmentData())->amount)->toBe(0);
});

it('quotes without a configured service', function (): void {
    config(['cargus.service_id' => null]);
    fakeCargus([CARGUS.'/ShippingCalculation' => Http::response(fixtureFile('shipping-calculation.json'))]);

    expect(Shipping::carrier('cargus')->quote(shipmentData())->serviceCode)->toBeNull();
});
