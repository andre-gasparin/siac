<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Features\Reports\Services\ReportDeliveryService;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportRecipient;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportDeliveryController extends Controller
{
    public function finalize(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
        ReportDeliveryService $delivery,
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $report = $reports->scopedReport($current_team, $report->id);
        $delivery->finalize($current_team, $report, $request->user());

        return back()->with('success', 'Relatório finalizado e envios enfileirados.');
    }

    public function resend(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
        ReportDeliveryService $delivery,
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $report = $reports->scopedReport($current_team, $report->id);
        $delivery->resend($current_team, $report, $request->user());

        return back()->with('success', 'Reenvio enfileirado.');
    }

    public function revoke(
        Request $request,
        Team $current_team,
        Report $report,
        ReportRecipient $recipient,
        ConsolidatedReportService $reports,
        ReportDeliveryService $delivery,
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $report = $reports->scopedReport($current_team, $report->id);
        $delivery->revoke($current_team, $report, $recipient, $request->user());

        return back()->with('success', 'Acesso revogado.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }
}
