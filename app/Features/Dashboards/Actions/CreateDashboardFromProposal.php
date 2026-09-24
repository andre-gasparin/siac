<?php

namespace App\Features\Dashboards\Actions;

use App\Models\Dashboard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDashboardFromProposal
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $user, Team $team): Dashboard
    {
        return DB::transaction(function () use ($data, $user, $team): Dashboard {
            $dashboard = Dashboard::create([
                'title' => $data['title'],
                'user_id' => $user->id,
                'team_id' => $team->id,
                'is_public' => $data['is_public'] ?? false,
            ]);

            foreach ($data['components'] as $componentData) {
                $dashboard->components()->create([
                    'type' => $componentData['type'],
                    'grid_config' => $componentData['grid_config'],
                    'settings' => $componentData['settings'] ?? [],
                ]);
            }

            return $dashboard;
        });
    }
}
