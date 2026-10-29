<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Cargus\CargusClient;
use WeBRTeu\Cargus\Models\CargusLocality;
use WeBRTeu\Shipping\Support\Normalizer;

class SyncLocationsCommand extends Command
{
    protected $signature = 'cargus:sync-locations';

    protected $description = 'Sincronizează județele și localitățile Cargus în cargus_localities';

    public function handle(CargusClient $client): int
    {
        $synced = 0;
        $counties = $client->get('/Counties', ['countryId' => 1])->json();

        foreach (is_array($counties) ? $counties : [] as $county) {
            if (! is_array($county) || ! is_numeric($county['CountyId'] ?? null) || ! is_scalar($county['Name'] ?? null)) {
                continue;
            }

            $localities = $client->get('/Localities', ['countryId' => 1, 'countyId' => (int) $county['CountyId']])->json();

            foreach (is_array($localities) ? $localities : [] as $locality) {
                if (! is_array($locality) || ! is_numeric($locality['LocalityId'] ?? null) || ! is_scalar($locality['Name'] ?? null)) {
                    continue;
                }

                CargusLocality::query()->updateOrCreate(['locality_id' => (int) $locality['LocalityId']], [
                    'county_id' => (int) $county['CountyId'],
                    'county' => (string) $county['Name'],
                    'name' => (string) $locality['Name'],
                    'county_key' => Normalizer::key((string) $county['Name']),
                    'name_key' => Normalizer::key((string) $locality['Name']),
                ]);

                $synced++;
            }
        }

        $this->info("Localități sincronizate: {$synced}.");

        return self::SUCCESS;
    }
}
