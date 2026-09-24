<?php

namespace App\Features\SpreadsheetImports\Http\Controllers;

use App\Features\SpreadsheetImports\Actions\CreateSpreadsheetTemplateAction;
use App\Features\SpreadsheetImports\Actions\DeleteSpreadsheetTemplateAction;
use App\Features\SpreadsheetImports\Actions\UpdateSpreadsheetTemplateAction;
use App\Features\SpreadsheetImports\Http\Requests\StoreSpreadsheetTemplateRequest;
use App\Features\SpreadsheetImports\Http\Requests\UpdateSpreadsheetTemplateRequest;
use App\Http\Controllers\Controller;
use App\Models\MonitoredSystem;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SpreadsheetTemplateController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $templates = SpreadsheetTemplate::query()
            ->where('team_id', $current_team->id)
            ->with(['creator:id,name', 'updater:id,name'])
            ->withCount('batches')
            ->orderBy('name')
            ->get();

        return Inertia::render('SpreadsheetImports/Templates/Index', [
            'templates' => $templates,
            'currentTeam' => $current_team,
        ]);
    }

    public function create(Request $request, Team $current_team): Response
    {
        $systems = MonitoredSystem::query()
            ->where('team_id', $current_team->id)
            ->where('is_active', true)
            ->with([
                'parameters' => function ($query): void {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->select([
                            'id',
                            'monitored_system_id',
                            'team_id',
                            'name',
                            'code',
                            'tag',
                            'unit',
                            'decimals',
                            'sort_order',
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'team_id', 'name', 'sort_order']);

        return Inertia::render('SpreadsheetImports/Templates/Editor', [
            'template' => null,
            'systems' => $systems,
            'currentTeam' => $current_team,
        ]);
    }

    public function store(
        StoreSpreadsheetTemplateRequest $request,
        Team $current_team,
        CreateSpreadsheetTemplateAction $action,
    ): RedirectResponse {
        $template = $action->execute(
            team: $current_team,
            user: $request->user(),
            data: $request->validated(),
        );

        return redirect()
            ->route('spreadsheet-imports.templates.index', ['current_team' => $current_team->slug])
            ->with('success', "Modelo '{$template->name}' criado com sucesso!");
    }

    public function edit(Request $request, Team $current_team, SpreadsheetTemplate $template): Response
    {
        abort_unless($template->team_id === $current_team->id, 404);

        $systems = MonitoredSystem::query()
            ->where('team_id', $current_team->id)
            ->where('is_active', true)
            ->with([
                'parameters' => function ($query): void {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->select([
                            'id',
                            'monitored_system_id',
                            'team_id',
                            'name',
                            'code',
                            'tag',
                            'unit',
                            'decimals',
                            'sort_order',
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'team_id', 'name', 'sort_order']);

        return Inertia::render('SpreadsheetImports/Templates/Editor', [
            'template' => $template,
            'systems' => $systems,
            'currentTeam' => $current_team,
        ]);
    }

    public function update(
        UpdateSpreadsheetTemplateRequest $request,
        Team $current_team,
        SpreadsheetTemplate $template,
        UpdateSpreadsheetTemplateAction $action,
    ): RedirectResponse {
        abort_unless($template->team_id === $current_team->id, 404);

        $action->execute(
            template: $template,
            user: $request->user(),
            data: $request->validated(),
        );

        return redirect()
            ->route('spreadsheet-imports.templates.index', ['current_team' => $current_team->slug])
            ->with('success', "Modelo '{$template->name}' atualizado com sucesso!");
    }

    public function samplePreview(Request $request, Team $current_team): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
        ]);

        $file = $request->file('file');
        $tempPath = $file->getRealPath();

        try {
            $reader = IOFactory::createReaderForFile($tempPath);
            $spreadsheet = $reader->load($tempPath);

            $sheets = [];
            foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
                $highestDataRow = $sheet->getHighestDataRow();
                $highestDataCol = $sheet->getHighestDataColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestDataCol);
                $highestRow = max($highestDataRow, 1);

                $cells = [];
                foreach ($sheet->getCellCollection()->getCoordinates() as $coord) {
                    $cell = $sheet->getCell($coord);
                    try {
                        $val = $cell->getCalculatedValue();
                    } catch (\Throwable) {
                        $val = $cell->getValue();
                    }

                    if ($val !== null && $val !== '') {
                        if (Date::isDateTime($cell)) {
                            try {
                                $dt = Date::excelToDateTimeObject((float) $val);
                                Date::roundMicroseconds($dt);
                                $formatCode = (string) $sheet->getStyle($coord)->getNumberFormat()->getFormatCode();
                                $hasTime = (bool) preg_match('/[hHsS]/', $formatCode);
                                $hasDate = (bool) preg_match('/[yYdD]/', $formatCode);
                                $hasSeconds = (bool) preg_match('/s/i', $formatCode);

                                $carbon = Carbon::instance($dt);
                                if (! $hasSeconds) {
                                    $carbon = $carbon->roundMinute();
                                }

                                if ($hasDate && $hasTime) {
                                    $val = $carbon->format('d/m/Y H:i');
                                } elseif ($hasTime && ! $hasDate) {
                                    $val = $carbon->format('H:i');
                                } else {
                                    $val = $carbon->format('d/m/Y');
                                }
                            } catch (\Throwable) {
                                // fallback
                            }
                        } elseif (is_numeric($val) && (float) $val >= 35000 && (float) $val <= 65000) {
                            $formatCode = strtolower((string) $sheet->getStyle($coord)->getNumberFormat()->getFormatCode());
                            if (str_contains($formatCode, 'y') || str_contains($formatCode, 'd') || str_contains($formatCode, 'm') || str_contains($formatCode, '/') || str_contains($formatCode, '-')) {
                                try {
                                    $dt = Date::excelToDateTimeObject((float) $val);
                                    Date::roundMicroseconds($dt);
                                    $hasTime = (bool) preg_match('/[hHsS]/', $formatCode);
                                    $hasSeconds = (bool) preg_match('/s/i', $formatCode);
                                    $carbon = Carbon::instance($dt);
                                    if (! $hasSeconds) {
                                        $carbon = $carbon->roundMinute();
                                    }

                                    if ($hasTime) {
                                        $val = $carbon->format('d/m/Y H:i');
                                    } else {
                                        $val = $carbon->format('d/m/Y');
                                    }
                                } catch (\Throwable) {
                                    // fallback
                                }
                            } else {
                                try {
                                    $formatted = (string) $cell->getFormattedValue();
                                    if (str_contains($formatted, '/') || str_contains($formatted, '-')) {
                                        $dt = Date::excelToDateTimeObject((float) $val);
                                        Date::roundMicroseconds($dt);
                                        $carbon = Carbon::instance($dt)->roundMinute();
                                        $val = $carbon->format('d/m/Y');
                                    }
                                } catch (\Throwable) {
                                    // fallback
                                }
                            }
                        } elseif (is_numeric($val) && (float) $val >= 0 && (float) $val < 1) {
                            // Horário como fração de dia (sem flag isDateTime do estilo)
                            $formatCode = strtolower((string) $sheet->getStyle($coord)->getNumberFormat()->getFormatCode());
                            if (str_contains($formatCode, 'h') || str_contains($formatCode, 'm') || str_contains($formatCode, 's')) {
                                try {
                                    $fraction = (float) $val;
                                    $totalSeconds = (int) round($fraction * 86400);
                                    if ($totalSeconds >= 86400) {
                                        $totalSeconds = 0;
                                    }
                                    $sec = $totalSeconds % 60;
                                    if ($sec === 59 || $sec === 1) {
                                        $totalSeconds = (int) round($totalSeconds / 60) * 60;
                                    }
                                    $h = intdiv($totalSeconds, 3600) % 24;
                                    $m = intdiv($totalSeconds % 3600, 60);
                                    $val = sprintf('%02d:%02d', $h, $m);
                                } catch (\Throwable) {
                                    // fallback
                                }
                            }
                        }

                        $cells[$coord] = is_string($val) ? trim((string) $val) : $val;
                    }
                }

                // Heurística de detecção de orientação (Horizontal vs Vertical)
                $suggestedOrientation = 'cell_reference';
                $suggestedDateRow = null;
                $suggestedDateColumn = null;
                $maxRowDates = 0;
                $bestDateRow = null;

                for ($r = 1; $r <= min($highestRow, 15); $r++) {
                    $dateCount = 0;
                    for ($c = 1; $c <= min($highestColIndex, 50); $c++) {
                        $coord = Coordinate::stringFromColumnIndex($c).$r;
                        $v = (string) ($cells[$coord] ?? '');
                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $v)) {
                            $dateCount++;
                        }
                    }
                    if ($dateCount >= 2 && $dateCount > $maxRowDates) {
                        $maxRowDates = $dateCount;
                        $bestDateRow = $r;
                    }
                }

                $maxColDates = 0;
                $bestDateCol = null;
                for ($c = 1; $c <= min($highestColIndex, 30); $c++) {
                    $dateCount = 0;
                    $colStr = Coordinate::stringFromColumnIndex($c);
                    for ($r = 1; $r <= min($highestRow, 100); $r++) {
                        $coord = $colStr.$r;
                        $v = (string) ($cells[$coord] ?? '');
                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $v)) {
                            $dateCount++;
                        }
                    }
                    if ($dateCount >= 2 && $dateCount > $maxColDates) {
                        $maxColDates = $dateCount;
                        $bestDateCol = $colStr;
                    }
                }

                if ($maxRowDates >= 2 && $maxRowDates >= $maxColDates) {
                    $suggestedOrientation = 'horizontal_series';
                    $suggestedDateRow = $bestDateRow;
                } elseif ($maxColDates >= 2) {
                    $suggestedOrientation = 'vertical_series';
                    $suggestedDateColumn = $bestDateCol;
                }

                $sheets[] = [
                    'index' => $index + 1,
                    'name' => $sheet->getTitle(),
                    'highest_row' => $highestRow,
                    'highest_column' => Coordinate::stringFromColumnIndex($highestColIndex),
                    'cells' => $cells,
                    'suggested_orientation' => $suggestedOrientation,
                    'suggested_date_row' => $suggestedDateRow,
                    'suggested_date_column' => $suggestedDateColumn,
                ];
            }

            return response()->json([
                'success' => true,
                'file_name' => $file->getClientOriginalName(),
                'sheets' => $sheets,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao ler arquivo de exemplo: '.$e->getMessage(),
            ], 422);
        }
    }

    public function destroy(
        Request $request,
        Team $current_team,
        SpreadsheetTemplate $template,
        DeleteSpreadsheetTemplateAction $action,
    ): RedirectResponse {
        abort_unless($template->team_id === $current_team->id, 404);

        $action->execute($template);

        return redirect()
            ->route('spreadsheet-imports.templates.index', ['current_team' => $current_team->slug])
            ->with('success', "Modelo '{$template->name}' removido com sucesso!");
    }
}
