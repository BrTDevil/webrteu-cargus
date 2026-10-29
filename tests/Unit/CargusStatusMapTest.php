<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use WeBRTeu\Cargus\CargusStatusMap;
use WeBRTeu\Shipping\Enums\ShipmentStatus;

it('maps Cargus event descriptions', function (string $label, ShipmentStatus $expected): void {
    expect(CargusStatusMap::resolve('1', $label))->toBe($expected);
})->with([
    ['Confirmat livrat', ShipmentStatus::Delivered],
    ['Preluat de curier', ShipmentStatus::PickedUp],
    ['In tranzit', ShipmentStatus::InTransit],
    ['Ajuns în punctul Ship & Go', ShipmentStatus::AtPickupPoint],
]);

it('honours an application override', function (): void {
    Config::set('shipping.status_map.cargus.21', 'delivered');

    expect(CargusStatusMap::resolve('21', 'x'))->toBe(ShipmentStatus::Delivered);
});
