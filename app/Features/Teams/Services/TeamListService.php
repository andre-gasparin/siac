<?php

namespace App\Features\Teams\Services;

use App\Features\Teams\Enums\TeamRole;
use App\Models\Membership;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamListService
{
    /**
     * @return array{status: string, search: string, role: string}
     */
    public function filters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $role = $request->string('role')->toString();

        return [
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'active',
            'search' => trim($request->string('search')->toString()),
            'role' => in_array($role, ['all', 'owner', 'admin', 'member'], true) ? $role : 'all',
        ];
    }

    /**
     * @param  array{status: string, search: string, role: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $teams = Team::query()
            ->select(['teams.id', 'teams.name', 'teams.slug', 'teams.is_active', 'teams.is_personal'])
            ->with([
                'members' => fn ($query) => $query
                    ->select(['users.id', 'users.name', 'users.email'])
                    ->orderByRaw('LOWER(users.name)'),
            ])
            ->withCount('members')
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('teams.is_active', $filters['status'] === 'active'),
            )
            ->when(
                $filters['role'] !== 'all',
                fn ($query) => $query->whereHas(
                    'memberships',
                    fn ($query) => $query->where('role', $filters['role']),
                ),
            )
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = "%{$filters['search']}%";

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('teams.name', 'like', $search)
                        ->orWhereHas('members', function ($query) use ($search): void {
                            $query
                                ->where('users.name', 'like', $search)
                                ->orWhere('users.email', 'like', $search);
                        });
                });
            })
            ->orderByRaw('LOWER(teams.name)')
            ->orderBy('teams.id')
            ->paginate(12)
            ->withQueryString();

        $activityByTeam = $this->parameterValueActivityByTeam(
            $teams->getCollection()->pluck('id'),
        );

        return $teams->through(fn (Team $team) => $this->teamListItem(
            $team,
            $activityByTeam[$team->id] ?? $this->emptyParameterValueActivity(),
        ));
    }

    /**
     * @param  list<array{date: string, label: string, count: int}>  $parameterValueActivity
     * @return array<string, mixed>
     */
    private function teamListItem(Team $team, array $parameterValueActivity): array
    {
        $owner = $team->members->first(function (User $member): bool {
            /** @var Membership $membership */
            $membership = $member->getRelation('pivot');

            return $membership->role === TeamRole::Owner;
        });

        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'isActive' => $team->is_active,
            'isPersonal' => $team->is_personal,
            'role' => TeamRole::Owner->value,
            'roleLabel' => $owner->name ?? TeamRole::Owner->label(),
            'isCurrent' => false,
            'membersCount' => $team->members_count,
            'members' => $team->members->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ]),
            'parameterValueActivity' => $parameterValueActivity,
            'canUpdate' => true,
            'canDelete' => ! $team->is_personal,
        ];
    }

    /**
     * @param  Collection<int, int>  $teamIds
     * @return array<int, list<array{date: string, label: string, count: int}>>
     */
    private function parameterValueActivityByTeam(Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        $today = CarbonImmutable::today();
        $startDate = $today->subDays(6)->toDateString();
        $exclusiveEndDate = $today->addDay()->toDateString();

        $countsByTeamAndDate = DB::table((new ParameterValue)->getTable())
            ->select(['team_id', 'measured_date'])
            ->selectRaw('COUNT(*) as values_count')
            ->whereIn('team_id', $teamIds->all())
            ->where('measured_date', '>=', $startDate)
            ->where('measured_date', '<', $exclusiveEndDate)
            ->groupBy('team_id', 'measured_date')
            ->get()
            ->groupBy('team_id')
            ->map(fn (Collection $rows): Collection => $rows->keyBy(
                fn (object $row): string => substr((string) $row->measured_date, 0, 10),
            ));

        $activityByTeam = [];

        foreach ($teamIds as $teamId) {
            $teamCounts = $countsByTeamAndDate->get($teamId, collect());

            $activityByTeam[$teamId] = array_values($this->lastSevenActivityDates()
                ->map(fn (array $date): array => [
                    'date' => $date['date'],
                    'label' => $date['label'],
                    'count' => (int) ($teamCounts->get($date['date'])->values_count ?? 0),
                ])
                ->all());
        }

        return $activityByTeam;
    }

    /**
     * @return list<array{date: string, label: string, count: int}>
     */
    private function emptyParameterValueActivity(): array
    {
        return array_values($this->lastSevenActivityDates()
            ->map(fn (array $date): array => [
                'date' => $date['date'],
                'label' => $date['label'],
                'count' => 0,
            ])
            ->all());
    }

    /**
     * @return Collection<int, array{date: string, label: string}>
     */
    private function lastSevenActivityDates(): Collection
    {
        $today = CarbonImmutable::today();

        return collect(range(6, 0))
            ->map(fn (int $daysAgo): array => [
                'date' => $today->subDays($daysAgo)->toDateString(),
                'label' => $today->subDays($daysAgo)->format('d/m/Y'),
            ]);
    }
}
