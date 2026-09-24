<?php

namespace App\Features\Teams\Http\Controllers;

use App\Features\Teams\Actions\LeaveTeam;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CurrentTeamController extends Controller
{
    public function switch(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->belongsToTeam($team) || $request->user()->is_admin, 403);

        $request->user()->switchTeam($team);

        return back();
    }

    public function leave(Request $request, Team $team, LeaveTeam $leaveTeam): RedirectResponse
    {
        Gate::authorize('leave', $team);

        $leaveTeam->handle($request->user(), $team);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You left the team ":name"', ['name' => $team->name]),
        ]);

        return to_route('teams.index');
    }
}
