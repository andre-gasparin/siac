<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $calculation_rule_id
 * @property int|null $user_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property list<int> $rule_ids
 * @property string $status
 * @property string|null $cursor_at
 * @property int $processed_count
 * @property int $total_count
 * @property int $created_count
 * @property int $updated_count
 * @property int $removed_count
 */
class CalculationRun extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'rule_ids' => 'array', 'finished_at' => 'datetime'];
    }
}
