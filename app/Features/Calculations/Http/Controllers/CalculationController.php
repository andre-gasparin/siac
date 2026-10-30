<?php

namespace App\Features\Calculations\Http\Controllers;

use App\Features\Calculations\Http\Requests\CalculationRuleRequest;
use App\Features\Calculations\Http\Requests\RecalculateRequest;
use App\Features\Calculations\Services\CalculationRuleService;
use App\Features\Calculations\Services\ParameterCalculationService;
use App\Http\Controllers\Controller;
use App\Jobs\RecalculateParameters;
use App\Models\CalculationRule;
use App\Models\CalculationRun;
use App\Models\MonitoredSystem;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CalculationController extends Controller
{
    public function __construct(private readonly CalculationRuleService $rules, private readonly ParameterCalculationService $calculator) {}

    public function index(Request $request, Team $current_team): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('Calculations/Index', [
            'currentTeam' => $current_team,
            'systems' => MonitoredSystem::query()->where('team_id', $current_team->id)->with(['parameters' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            'rules' => CalculationRule::query()->where('team_id', $current_team->id)->orderBy('name')->get(),
            'runs' => CalculationRun::query()->where('team_id', $current_team->id)->latest()->limit(20)->get(),
            'types' => ParameterCalculationService::TYPES,
        ]);
    }

    public function store(CalculationRuleRequest $request, Team $current_team): RedirectResponse
    {
        $this->rules->save($current_team, $request->validated());

        return back()->with('success', 'Cálculo cadastrado.');
    }

    public function update(CalculationRuleRequest $request, Team $current_team, CalculationRule $calculation): RedirectResponse
    {
        abort_unless($calculation->team_id === $current_team->id, 404);
        $this->rules->save($current_team, $request->validated(), $calculation);

        return back()->with('success', 'Cálculo atualizado. Use Recalcular para atualizar o histórico.');
    }

    public function destroy(Request $request, Team $current_team, CalculationRule $calculation): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless($calculation->team_id === $current_team->id, 404);
        $this->rules->delete($current_team, $calculation);

        return back()->with('success', 'Cálculo excluído. Os valores históricos foram preservados.');
    }

    public function recalculate(RecalculateRequest $request, Team $current_team, CalculationRule $calculation): RedirectResponse
    {
        abort_unless($calculation->team_id === $current_team->id, 404);
        $run = DB::transaction(function () use ($request, $current_team, $calculation): CalculationRun {
            $this->calculator->lock($current_team);
            $this->rules->ensureIdle($current_team);
            $calculation = CalculationRule::query()->where('team_id', $current_team->id)->findOrFail($calculation->id);
            $run = CalculationRun::query()->create($request->validated() + [
                'team_id' => $current_team->id, 'calculation_rule_id' => $calculation->id, 'user_id' => $request->user()?->id,
                'rule_ids' => $this->calculator->historicalRuleIds($current_team, $calculation),
            ]);

            return $run;
        }, 3);
        try {
            RecalculateParameters::dispatch($run->id)->afterCommit();
        } catch (Throwable $exception) {
            (new RecalculateParameters($run->id))->failed($exception);
            report($exception);

            return back()->withErrors(['calculation' => 'Não foi possível enfileirar o recálculo. Tente novamente.']);
        }

        return back()->with('success', 'Recálculo enfileirado. Acompanhe o andamento abaixo.');
    }
}
