<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Facades;

use Illuminate\Support\Facades\Facade;
use WeBRTeu\Cargus\CargusCarrier;

/**
 * @method static \WeBRTeu\Shipping\Data\AwbData createShipment(\WeBRTeu\Shipping\Data\ShipmentData $shipment)
 * @method static \WeBRTeu\Shipping\Data\TrackingData track(string $awb)
 * @method static string orderPickup(\WeBRTeu\Shipping\Data\PickupRequestData $request)
 * @method static \WeBRTeu\Cargus\CargusClient client()
 *
 * @see CargusCarrier
 */
class Cargus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CargusCarrier::class;
    }
}
