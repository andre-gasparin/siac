<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;

test('backend behavior follows the feature boundaries', function () {
    $legacyDirectories = [
        app_path('Actions'),
        app_path('Concerns'),
        app_path('Data'),
        app_path('Enums'),
        app_path('Http/Controllers'),
        app_path('Http/Requests'),
        app_path('Notifications'),
        app_path('Policies'),
        app_path('Rules'),
        app_path('Services'),
    ];

    $allowedLegacyFiles = [
        'app/Actions/Fortify/CreateNewUser.php',
        'app/Actions/Fortify/ResetUserPassword.php',
        'app/Concerns/PasswordValidationRules.php',
        'app/Concerns/ProfileValidationRules.php',
        'app/Http/Controllers/Controller.php',
    ];

    $legacyFiles = collect($legacyDirectories)
        ->filter(fn (string $directory): bool => File::isDirectory($directory))
        ->flatMap(fn (string $directory) => File::allFiles($directory))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->map(fn (SplFileInfo $file): string => str_replace(
            '\\',
            '/',
            'app/'.ltrim(str_replace(app_path(), '', $file->getPathname()), '\\/'),
        ))
        ->sort()
        ->values()
        ->all();

    expect($legacyFiles)->toBe($allowedLegacyFiles);

    foreach ([base_path('routes/web.php'), base_path('routes/settings.php')] as $aggregator) {
        expect(File::get($aggregator))
            ->not->toContain('Route::')
            ->toContain('require ');
    }

    $featureClasses = collect(File::allFiles(app_path('Features')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->reject(fn (SplFileInfo $file): bool => str_contains(
            str_replace('\\', '/', $file->getPathname()),
            '/Routes/',
        ));

    foreach ($featureClasses as $file) {
        $relativeDirectory = trim(str_replace(
            '\\',
            '/',
            str_replace(app_path('Features'), '', $file->getPath()),
        ), '/');
        $namespace = 'App\\Features\\'.str_replace('/', '\\', $relativeDirectory);

        expect(File::get($file->getPathname()))->toContain("namespace {$namespace};");
    }
});

test('application route contract remains stable', function () {
    $expectedRoutes = [
        ['GET|HEAD', '.well-known/passkey-endpoints', 'well-known.passkeys', ['web']],
        ['DELETE', 'invitations/{invitation}', 'invitations.decline', ['web', 'auth']],
        ['GET|HEAD', 'invitations/{invitation}/accept', 'invitations.accept', ['web', 'auth']],
        ['GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS', 'settings', null, ['web', 'auth']],
        ['PUT', 'settings/password', 'user-password.update', ['web', 'auth', 'verified', 'throttle:6,1']],
        ['GET|HEAD', 'settings/profile', 'profile.edit', ['web', 'auth']],
        ['PATCH', 'settings/profile', 'profile.update', ['web', 'auth']],
        ['DELETE', 'settings/profile', 'profile.destroy', ['web', 'auth', 'verified']],
        ['GET|HEAD', 'settings/security', 'security.edit', ['web', 'auth', 'verified', 'Illuminate\Auth\Middleware\RequirePassword']],
        ['GET|HEAD', 'unidades', 'teams.index', ['web', 'auth', 'verified']],
        ['POST', 'unidades', 'teams.store', ['web', 'auth', 'verified']],
        ['GET|HEAD', 'unidades/{team}', 'teams.edit', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['PATCH', 'unidades/{team}', 'teams.update', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['DELETE', 'unidades/{team}', 'teams.destroy', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['POST', 'unidades/{team}/invitations', 'teams.invitations.store', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['DELETE', 'unidades/{team}/invitations/{invitation}', 'teams.invitations.destroy', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['DELETE', 'unidades/{team}/leave', 'teams.leave', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['PATCH', 'unidades/{team}/members/{user}', 'teams.members.update', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['DELETE', 'unidades/{team}/members/{user}', 'teams.members.destroy', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', 'unidades/{team}/sistemas', 'teams.systems.edit', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['PUT', 'unidades/{team}/sistemas', 'teams.systems.update', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['POST', 'unidades/{team}/switch', 'teams.switch', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', '{current_team}/dashboard', 'dashboard', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', '{current_team}/dashboards', 'dashboards.index', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['POST', '{current_team}/dashboards', 'dashboards.store', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['POST', '{current_team}/dashboards/ai-create', 'dashboards.ai-create', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['POST', '{current_team}/dashboards/ai-suggest', 'dashboards.ai-suggest', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', '{current_team}/dashboards/components/{component}/data', 'dashboards.components.data', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', '{current_team}/dashboards/{dashboard}', 'dashboards.show', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['PUT', '{current_team}/dashboards/{dashboard}', 'dashboards.update', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['DELETE', '{current_team}/dashboards/{dashboard}', 'dashboards.destroy', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['POST', '{current_team}/dashboards/{dashboard}/components', 'dashboards.components.store', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['PUT', '{current_team}/dashboards/{dashboard}/components/{component}', 'dashboards.components.update', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['DELETE', '{current_team}/dashboards/{dashboard}/components/{component}', 'dashboards.components.destroy', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['PATCH', '{current_team}/dashboards/{dashboard}/grid', 'dashboards.grid.update', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', '{current_team}/parameters/search', 'parameters.search', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', '{current_team}/tabela-dados', 'data-table.index', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['GET|HEAD', '{current_team}/tabela-dados/data', 'data-table.data', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
        ['DELETE', '{current_team}/tabela-dados/rows', 'data-table.rows.destroy', ['web', 'auth', 'verified', 'App\Http\Middleware\EnsureTeamMembership']],
    ];

    $expectedUris = array_column($expectedRoutes, 1);
    $actualRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array($route->uri(), $expectedUris, true))
        ->map(fn (Route $route): array => [
            implode('|', $route->methods()),
            $route->uri(),
            $route->getName(),
            $route->gatherMiddleware(),
        ])
        ->values()
        ->all();

    $this->assertEqualsCanonicalizing($expectedRoutes, $actualRoutes);
});

test('frontend uses named routes instead of application controller actions', function () {
    $applicationControllerImports = collect(File::allFiles(resource_path('js')))
        ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['ts', 'vue'], true))
        ->filter(fn (SplFileInfo $file): bool => str_contains(
            File::get($file->getPathname()),
            '@/actions/App/Http/Controllers',
        ));

    expect($applicationControllerImports)->toBeEmpty();
});
