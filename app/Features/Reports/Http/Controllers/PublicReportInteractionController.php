<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Features\Reports\Services\ReportDeliveryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicReportInteractionController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ConsolidatedReportService $reports,
        ReportDeliveryService $delivery,
    ): RedirectResponse {
        $validated = $request->validate([
            'comment_ids' => ['required', 'array', 'min:1'],
            'comment_ids.*' => ['required', 'integer'],
        ]);

        $recipient = $reports->recipient($token);
        $count = $delivery->notifyInteractions(
            team: $recipient->report->team,
            report: $recipient->report,
            commentIds: array_values(array_map('intval', $validated['comment_ids'])),
            recipient: $recipient,
        );

        return back()->with('success', "Reenvio do relatório enfileirado com {$count} interação(ões).");
    }
}
