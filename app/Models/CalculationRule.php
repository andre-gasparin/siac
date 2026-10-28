<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $type
 * @property int $primary_parameter_id
 * @property int $secondary_parameter_id
 * @property int $destination_parameter_id
 * @property float|null $primary_multiplier
 * @property float|null $secondary_multiplier
 * @property float|null $result_multiplier
 */
class CalculationRule extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['primary_multiplier' => 'double', 'secondary_multiplier' => 'double', 'result_multiplier' => 'double'];
    }
}
