<?php

declare(strict_types=1);

use WeBRTeu\Cargus\Models\CargusLocality;
use WeBRTeu\Cargus\Requests\AwbPayload;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;

function awbPayload(array $overrides = [], string $codType = 'cash', bool $open = false, ?int $location = 201): array
{
    return (new AwbPayload($location, 34, 38, $codType, $open))->make(shipmentData($overrides));
}

it('sends cash on delivery as CashRepayment only', function (): void {
    $payload = awbPayload();

    expect($payload['CashRepayment'])->toBe(149.7)
        ->and($payload['BankRepayment'])->toBe(0)
        ->and($payload['Sender'])->toBe(['LocationId' => 201])
        ->and($payload['CustomString'])->toBe('CMD-1024')
        ->and($payload['TotalWeight'])->toBe(2)
        ->and($payload['Parcels'])->toBe(2)
        ->and($payload['DeclaredValue'])->toBe(200.0)
        ->and($payload['ServiceId'])->toBe(34)
        ->and($payload['OpenPackage'])->toBeFalse()
        ->and($payload)->not->toHaveKey('DeliveryPudoPoint');
});

it('sends bank repayment only when configured, never both', function (): void {
    $payload = awbPayload(codType: 'bank');

    expect($payload['BankRepayment'])->toBe(149.7)
        ->and($payload['CashRepayment'])->toBe(0);
});

it('formats the recipient and uses the synced locality ids', function (): void {
    CargusLocality::query()->create(['county_id' => 8, 'locality_id' => 501, 'county' => 'Brasov', 'name' => 'Rasnov', 'county_key' => 'brasov', 'name_key' => 'rasnov']);

    $recipient = awbPayload()['Recipient'];

    expect($recipient)->toMatchArray([
        'Name' => 'Ion Popescu',
        'CountyName' => 'Brasov',
        'LocalityName' => 'Rasnov',
        'CountyId' => 8,
        'LocalityId' => 501,
        'AddressText' => 'Str. Republicii nr. 10 bl. A ap. 3',
        'PhoneNumber' => '0721000000',
    ]);
});

it('keeps the given names when the locality is not synced', function (): void {
    expect(awbPayload()['Recipient'])->toMatchArray(['CountyName' => 'Brașov', 'LocalityName' => 'Râșnov'])
        ->not->toHaveKey('LocalityId');
});

it('delivers to Ship & Go with the PUDO service', function (): void {
    $payload = awbPayload(['pickupPointId' => '301']);

    expect($payload['DeliveryPudoPoint'])->toBe(301)
        ->and($payload['ServiceId'])->toBe(38);
});

it('uses the shipment service and the open package flags', function (): void {
    expect(awbPayload(['serviceCode' => '1', 'openPackage' => true, 'saturdayDelivery' => true]))
        ->toMatchArray(['ServiceId' => 1, 'OpenPackage' => true, 'SaturdayDelivery' => true])
        ->and(awbPayload(open: true)['OpenPackage'])->toBeTrue()
        ->and((new AwbPayload(201, 34, null))->serviceId(shipmentData(['pickupPointId' => '1'])))->toBe(34);
});

it('uses the company name for companies', function (): void {
    $address = new AddressData('Ion', '0721000000', 'Cluj', 'Cluj', 'Str. X', '1', company: 'Exemplu SRL');

    expect(awbPayload(['recipient' => $address])['Recipient'])->toMatchArray(['Name' => 'Exemplu SRL', 'ContactPerson' => 'Ion']);
});

it('requires the pickup location and rejects a custom sender', function (): void {
    expect(fn () => awbPayload(location: null))->toThrow(ShipmentValidationException::class, 'CARGUS_PICKUP_LOCATION_ID')
        ->and(fn () => awbPayload(['sender' => new AddressData('F', '0700000000', 'Cluj', 'Cluj', 'X', '1')]))->toThrow(UnsupportedOperationException::class);
});
