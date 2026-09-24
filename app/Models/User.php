<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Features\Teams\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Passkey;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property bool $is_admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Passkey> $passkeys
 * @property-read Collection<int, Report> $reportsCreated
 * @property-read Collection<int, Report> $reportsFinished
 * @property-read Collection<int, ChartTemplate> $chartTemplates
 */
#[Fillable(['name', 'email', 'password', 'current_team_id', 'is_admin'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Get the reports created by the user.
     *
     * @return HasMany<Report, $this>
     */
    public function reportsCreated(): HasMany
    {
        return $this->hasMany(Report::class, 'created_by');
    }

    /**
     * Get the reports completed/finished by the user.
     *
     * @return HasMany<Report, $this>
     */
    public function reportsFinished(): HasMany
    {
        return $this->hasMany(Report::class, 'finished_by');
    }

    /**
     * Determine if the user belongs to at least one active team.
     */
    public function hasActiveTeam(): bool
    {
        if ($this->is_admin) {
            return Team::query()->where('is_active', true)->exists();
        }

        return $this->teams()->where('teams.is_active', true)->exists();
    }

    /**
     * Get the chart templates created by the user.
     *
     * @return HasMany<ChartTemplate, $this>
     */
    public function chartTemplates(): HasMany
    {
        return $this->hasMany(ChartTemplate::class);
    }
}
