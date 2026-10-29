<?php

declare(strict_types=1);

use WeBRTeu\Shipping\Facades\Shipping;

it('creates, prints and deletes an AWB on the real API', function (): void {
    $carrier = Shipping::carrier('cargus');
    $awb = $carrier->createShipment(shipmentData(['reference' => 'SMOKE-'.time()]));

    expect($carrier->label($awb->awb))->toStartWith('%PDF')
        ->and($carrier->cancel($awb->awb))->toBeTrue();
})->group('sandbox')->skip(! env('CARGUS_SANDBOX_SUBSCRIPTION_KEY'), 'Setează CARGUS_SANDBOX_SUBSCRIPTION_KEY / _USERNAME / _PASSWORD / _LOCATION_ID.');
