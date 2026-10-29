<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Cargus\CargusCarrier;
use WeBRTeu\Cargus\Models\CargusPudoPoint;
use WeBRTeu\Shipping\Data\PickupPointData;

class SyncPudoCommand extends Command
{
    protected $signature = 'cargus:sync-pudo';

    protected $description = 'Sincronizează punctele Ship & Go în cargus_pudo_points';

    public function handle(CargusCarrier $cargus): int
    {
        $points = $cargus->remotePickupPoints();

        foreach ($points as $point) {
            CargusPudoPoint::query()->updateOrCreate(['pudo_id' => (int) $point->id], [
                'name' => $point->name,
                'county' => $point->county,
                'city' => $point->city,
                'address' => $point->address,
                'postal_code' => $point->postalCode,
                'lat' => $point->lat,
                'lng' => $point->lng,
                'supports_cod' => $point->supportsCod,
                'active' => true,
                'raw' => $point->raw,
            ]);
        }

        $deactivated = $points->isEmpty() ? 0 : CargusPudoPoint::query()->whereNotIn('pudo_id', $points->map(fn (PickupPointData $point): int => (int) $point->id)->all())->update(['active' => false]);

        $this->info("Puncte Ship & Go sincronizate: {$points->count()}, dezactivate: {$deactivated}.");

        return self::SUCCESS;
    }
}
