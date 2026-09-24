<?php

namespace App\Features\Teams\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteTeam
{
    public function handle(User $user, Team $team): void
    {
        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($fallbackTeam, $team, $user): void {
            User::query()
                ->where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTeam($affectedUser->personalTeam()));

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();

            if ($fallbackTeam !== null) {
                $user->switchTeam($fallbackTeam);
            }
        });
    }
}
