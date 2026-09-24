<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Agents\Contracts\ReportAgent;
use App\Features\Reports\Http\Requests\ReportAssistantRequest;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class ReportAssistantController extends Controller
{
    public function __invoke(
        ReportAssistantRequest $request,
        Team $current_team,
        ReportAgent $assistant,
    ): JsonResponse {
        $validated = $request->validated();

        return response()->json($assistant->respond(
            $current_team,
            (int) $validated['system_id'],
            $validated['date_reference'],
            $validated['current_text'] ?? '',
            $validated['messages'] ?? [],
        ));
    }
}
