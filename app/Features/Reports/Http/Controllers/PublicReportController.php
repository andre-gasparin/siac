<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicReportController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ConsolidatedReportService $reports,
    ): Response {
        $recipient = $reports->recipient($token);
        $view = $reports->publicView($recipient);
        $reports->recordPublicAccess($recipient);

        $response = Inertia::render('Reports/Public', $view)->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
