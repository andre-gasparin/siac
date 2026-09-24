<?php

namespace App\Features\DataTable\Http\Controllers;

use App\Features\DataTable\Http\Requests\DataTableDataRequest;
use App\Features\DataTable\Services\DataTableDataBuilder;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class DataTableDataController extends Controller
{
    public function __invoke(
        DataTableDataRequest $request,
        Team $current_team,
        DataTableDataBuilder $dataBuilder,
    ): JsonResponse {
        return response()->json(
            $dataBuilder->build($current_team, $request->validated()),
        );
    }
}
