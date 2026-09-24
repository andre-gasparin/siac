<?php

namespace App\Features\Teams\Http\Controllers;

use App\Features\Teams\Services\TeamListService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamIndexController extends Controller
{
    public function __invoke(Request $request, TeamListService $teamList): Response
    {
        abort_unless($request->user()->is_admin, 403);

        $filters = $teamList->filters($request);

        return Inertia::render('teams/Index', [
            'teams' => Inertia::scroll(fn () => $teamList->paginate($filters)),
            'filters' => $filters,
            'statusCounts' => [
                'active' => Team::query()->where('is_active', true)->count(),
                'inactive' => Team::query()->where('is_active', false)->count(),
            ],
        ]);
    }
}
