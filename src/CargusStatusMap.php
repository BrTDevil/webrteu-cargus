<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus;

use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Support\StatusResolver;

/**
 * `EventId` din AwbTrace → ShipmentStatus.
 *
 * ⚠️ Neverificat pe API real: harta e goală până la primele trasee reale; statusul se deduce din descrierea
 * evenimentului (cuvinte cheie în română) și se poate suprascrie din `shipping.status_map.cargus`.
 */
final class CargusStatusMap
{
    /**
     * @var array<array-key, ShipmentStatus>
     */
    public const MAP = [];

    public static function resolve(string $code, string $label): ShipmentStatus
    {
        return StatusResolver::resolve('cargus', $code, $label, self::MAP);
    }
}
