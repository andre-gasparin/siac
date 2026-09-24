<?php

namespace App\Features\Teams\Http\Controllers;

use App\Features\Teams\Actions\CreateTeam;
use App\Features\Teams\Actions\DeleteTeam;
use App\Features\Teams\Data\TeamPermissions;
use App\Features\Teams\Enums\TeamRole;
use App\Features\Teams\Http\Requests\DeleteTeamRequest;
use App\Features\Teams\Http\Requests\SaveTeamRequest;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $team = $createTeam->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function edit(Request $request, Team $team): Response
    {
        $user = $request->user();

        return Inertia::render('teams/Edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'isActive' => (bool) $team->is_active,
                'isPersonal' => $team->is_personal,
            ],
            'members' => $team->members()->get()->map(function (User $member): array {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => $member->avatar ?? null,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            }),
            'invitations' => $team->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn ($invitation): array => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->is_admin
                ? new TeamPermissions(
                    canUpdateTeam: true,
                    canDeleteTeam: ! $team->is_personal,
                    canAddMember: true,
                    canUpdateMember: true,
                    canRemoveMember: true,
                    canCreateInvitation: true,
                    canCancelInvitation: true,
                )
                : $user->toTeamPermissions($team),
            'availableRoles' => TeamRole::assignable(),
        ]);
    }

    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team): Team {
            $lockedTeam = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $data = ['name' => $request->validated('name')];
            if ($request->has('is_active')) {
                $data['is_active'] = $request->boolean('is_active');
            }
            $lockedTeam->update($data);

            return $lockedTeam;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return back();
    }

    public function destroy(
        DeleteTeamRequest $request,
        Team $team,
        DeleteTeam $deleteTeam,
    ): RedirectResponse {
        $deleteTeam->handle($request->user(), $team);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('teams.index');
    }
}
