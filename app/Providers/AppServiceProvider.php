<?php

namespace App\Providers;

use App\Features\Reports\Agents\Contracts\ReportAgent;
use App\Features\Reports\Agents\ReportWritingAgent;
use App\Models\Passkey;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passkeys::usePasskeyModel(Passkey::class);
        $this->app->bind(ReportAgent::class, ReportWritingAgent::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        RateLimiter::for('reports-ai', function (Request $request): Limit {
            $team = $request->route('current_team');
            $teamId = is_object($team) ? $team->id : (string) $team;

            return Limit::perMinute(10)->by($request->user()->id.':'.$teamId);
        });

        RateLimiter::for('reports-public-view', function (Request $request): Limit {
            return Limit::perMinute(120)->by(hash('sha256', (string) $request->route('token')).':'.$request->ip());
        });

        RateLimiter::for('reports-public-comments', function (Request $request): Limit {
            return Limit::perMinute(10)->by(hash('sha256', (string) $request->route('token')).':'.$request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
