<?php

namespace App\Features\Teams\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveTeam
{
    public function handle(User $user, Team $team): void
    {
        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($fallbackTeam, $team, $user): void {
            $team->memberships()
                ->where('user_id', $user->id)
                ->delete();

            if ($fallbackTeam !== null) {
                $user->switchTeam($fallbackTeam);
            }
        });
    }
}
