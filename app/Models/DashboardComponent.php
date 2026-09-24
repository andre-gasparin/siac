<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dashboard_id
 * @property string $type
 * @property array<string, mixed> $grid_config
 * @property array<string, mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Dashboard $dashboard
 */
#[Fillable([
    'dashboard_id',
    'type',
    'grid_config',
    'settings',
])]
class DashboardComponent extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the dashboard that owns the component.
     *
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grid_config' => 'array',
            'settings' => 'array',
        ];
    }
}
