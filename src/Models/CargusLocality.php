<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property int $county_id
 * @property int $locality_id
 * @property string $county
 * @property string $name
 * @property string $county_key
 * @property string $name_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CargusLocality extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Config::string('cargus.tables.localities', 'cargus_localities');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['county_id' => 'integer', 'locality_id' => 'integer'];
    }
}
