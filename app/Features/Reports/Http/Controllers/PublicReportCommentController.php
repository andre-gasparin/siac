<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicReportCommentController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ConsolidatedReportService $reports,
    ): RedirectResponse {
        $validated = $request->validate([
            'report_item_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/'],
        ]);
        $recipient = $reports->recipient($token);
        $reports->addPublicComment(
            $recipient,
            (int) $validated['report_item_id'],
            $validated['body'],
        );

        return back()->with('success', 'Comentário publicado.');
    }
}
