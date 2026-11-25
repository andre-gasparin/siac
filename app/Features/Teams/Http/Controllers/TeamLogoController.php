<?php

namespace App\Features\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamLogoController extends Controller
{
    public function __invoke(Team $team): StreamedResponse
    {
        abort_unless(
            $team->logo_path !== null && Storage::disk('public')->exists($team->logo_path),
            404,
        );

        return Storage::disk('public')->response($team->logo_path, headers: [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
