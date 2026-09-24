<?php

namespace App\Features\SpreadsheetImports\Http\Controllers;

use App\Features\SpreadsheetImports\Http\Requests\StoreSpreadsheetEmailRuleRequest;
use App\Features\SpreadsheetImports\Http\Requests\UpdateSpreadsheetEmailRuleRequest;
use App\Http\Controllers\Controller;
use App\Models\SpreadsheetEmailRule;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpreadsheetEmailRuleController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $rules = SpreadsheetEmailRule::query()
            ->where('team_id', $current_team->id)
            ->with(['template:id,name', 'creator:id,name', 'updater:id,name'])
            ->withCount('inboxItems')
            ->orderBy('priority', 'desc')
            ->orderBy('name', 'asc')
            ->paginate(15);

        return Inertia::render('SpreadsheetImports/EmailRules/Index', [
            'currentTeam' => $current_team,
            'rules' => $rules,
        ]);
    }

    public function create(Request $request, Team $current_team): Response
    {
        $templates = SpreadsheetTemplate::query()
            ->where('team_id', $current_team->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return Inertia::render('SpreadsheetImports/EmailRules/Form', [
            'currentTeam' => $current_team,
            'templates' => $templates,
            'rule' => null,
        ]);
    }

    public function store(StoreSpreadsheetEmailRuleRequest $request, Team $current_team): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        SpreadsheetEmailRule::create([
            ...$validated,
            'team_id' => $current_team->id,
            'is_active' => $validated['is_active'] ?? true,
            'priority' => $validated['priority'] ?? 0,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('spreadsheet-imports.rules.index', ['current_team' => $current_team->slug])
            ->with('success', 'Regra de leitura de e-mail criada com sucesso!');
    }

    public function edit(Request $request, Team $current_team, SpreadsheetEmailRule $rule): Response
    {
        abort_unless($rule->team_id === $current_team->id, 404);

        $templates = SpreadsheetTemplate::query()
            ->where('team_id', $current_team->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return Inertia::render('SpreadsheetImports/EmailRules/Form', [
            'currentTeam' => $current_team,
            'templates' => $templates,
            'rule' => $rule,
        ]);
    }

    public function update(
        UpdateSpreadsheetEmailRuleRequest $request,
        Team $current_team,
        SpreadsheetEmailRule $rule,
    ): RedirectResponse {
        abort_unless($rule->team_id === $current_team->id, 404);

        $validated = $request->validated();
        $user = $request->user();

        $rule->update([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'priority' => $validated['priority'] ?? 0,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('spreadsheet-imports.rules.index', ['current_team' => $current_team->slug])
            ->with('success', 'Regra de leitura de e-mail atualizada com sucesso!');
    }

    public function destroy(Request $request, Team $current_team, SpreadsheetEmailRule $rule): RedirectResponse
    {
        abort_unless($rule->team_id === $current_team->id, 404);

        $rule->delete();

        return redirect()
            ->route('spreadsheet-imports.rules.index', ['current_team' => $current_team->slug])
            ->with('success', 'Regra de leitura de e-mail excluída com sucesso.');
    }
}
