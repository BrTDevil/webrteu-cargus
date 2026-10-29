<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Cargus\CargusCarrier;

class PickupLocationsCommand extends Command
{
    protected $signature = 'cargus:pickup-locations';

    protected $description = 'Afișează LocationId-urile punctelor de ridicare ale contului Cargus';

    public function handle(CargusCarrier $cargus): int
    {
        $this->table(['LocationId', 'Nume', 'Localitate', 'Adresă'], array_map(fn (array $location): array => [
            is_scalar($location['LocationId'] ?? null) ? (string) $location['LocationId'] : '',
            is_scalar($location['Name'] ?? null) ? (string) $location['Name'] : '',
            is_scalar($location['LocalityName'] ?? null) ? (string) $location['LocalityName'] : '',
            is_scalar($location['AddressText'] ?? null) ? (string) $location['AddressText'] : '',
        ], $cargus->rawPickupLocations()));

        return self::SUCCESS;
    }
}
