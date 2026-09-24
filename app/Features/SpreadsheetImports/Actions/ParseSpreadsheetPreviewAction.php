<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use Carbon\Carbon;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParseSpreadsheetPreviewAction
{
    /**
     * @return array{
     *     total_extracted: int,
     *     total_new: int,
     *     total_updates: int,
     *     total_empty_or_invalid: int,
     *     items: array<int, array{
     *         sheet_name: string,
     *         cell: string,
     *         system_id: int,
     *         system_name: string,
     *         parameter_id: int,
     *         parameter_name: string,
     *         unit: string|null,
     *         raw_value: mixed,
     *         multiplier: float,
     *         final_value: float|null,
     *         measured_at: string,
     *         measured_date: string,
     *         is_update: bool,
     *         existing_value: float|null,
     *         status: string,
     *         error_message: string|null,
     *     }>,
     *     warnings: array<int, string>,
     *     sheets_found: array<int, string>,
     * }
     */
    public function execute(
        Team $team,
        SpreadsheetTemplate $template,
        string $filePath,
        ?string $referenceDate = null,
        ?string $dateScope = null,
    ): array {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        $sheetNames = $spreadsheet->getSheetNames();
        $config = $template->config;
        $sheetsConfig = $config['sheets'] ?? [];

        $items = [];
        $warnings = [];
        $totalNew = 0;
        $totalUpdates = 0;
        $totalEmptyOrInvalid = 0;

        // Cache parameters belonging to this team for quick lookup
        $parameters = Parameter::query()
            ->where('team_id', $team->id)
            ->with('monitoredSystem')
            ->get()
            ->keyBy('id');

        foreach ($sheetsConfig as $sheetIndex => $sheetConfig) {
            $sheet = $this->resolveWorksheet($spreadsheet, $sheetConfig, $sheetIndex);

            if (! $sheet) {
                $identifier = $sheetConfig['sheet_name'] ?? ($sheetConfig['sheet_identifier_value'] ?? $sheetIndex + 1);
                $warnings[] = "Aba '{$identifier}' não encontrada no arquivo enviado.";

                continue;
            }

            $currentSheetName = $sheet->getTitle();
            $dateMode = $sheetConfig['date_mode'] ?? 'cell_reference';
            $defaultDateCell = $sheetConfig['date_cell'] ?? null;
            $defaultTimeCell = $sheetConfig['time_cell'] ?? null;
            $mappings = $sheetConfig['mappings'] ?? [];
            $isAllDates = ($dateScope === 'all') || (empty($dateScope) && $dateMode === 'all_dates_scan');
            $ignoreEmptyCells = $this->shouldIgnoreEmptyCells($sheetConfig, $config);

            if ($dateMode === 'cell_reference') {
                foreach ($mappings as $mapping) {
                    $cellCoordinate = strtoupper(trim((string) ($mapping['cell'] ?? '')));
                    if (empty($cellCoordinate)) {
                        continue;
                    }

                    $paramId = (int) ($mapping['parameter_id'] ?? 0);
                    $parameter = $parameters->get($paramId);

                    if (! $parameter) {
                        $warnings[] = "Parâmetro ID {$paramId} (célula {$cellCoordinate}) não pertence à unidade atual ou foi removido.";

                        continue;
                    }

                    $dateCell = strtoupper(trim((string) ($mapping['date_cell'] ?? $defaultDateCell ?? '')));
                    $timeCell = strtoupper(trim((string) ($mapping['time_cell'] ?? $defaultTimeCell ?? '')));
                    $multiplier = (float) ($mapping['multiplier'] ?? 1.0);

                    // Resolve Date & Time
                    $dateTime = $this->extractDateTimeFromCells($sheet, $dateCell, $timeCell, $referenceDate);
                    $rawValue = $this->getEvaluatedCellValue($sheet->getCell($cellCoordinate));

                    $numericValue = $this->normalizeNumericValue($rawValue);

                    if ($numericValue === null) {
                        $isCellEmpty = $this->isCellEmpty($rawValue);

                        if ($isCellEmpty && ! $ignoreEmptyCells) {
                            $existing = ParameterValue::query()
                                ->where('team_id', $team->id)
                                ->where('parameter_id', $parameter->id)
                                ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                ->first();

                            $isUpdate = $existing !== null;
                            if ($isUpdate) {
                                $totalUpdates++;
                            } else {
                                $totalNew++;
                            }

                            $items[] = [
                                'sheet_name' => $currentSheetName,
                                'cell' => $cellCoordinate,
                                'system_id' => $parameter->monitored_system_id,
                                'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                'parameter_id' => $parameter->id,
                                'parameter_name' => $parameter->name,
                                'unit' => $parameter->unit,
                                'raw_value' => $rawValue,
                                'multiplier' => $multiplier,
                                'final_value' => null,
                                'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                'measured_date' => $dateTime->format('Y-m-d'),
                                'is_update' => $isUpdate,
                                'existing_value' => $existing?->value,
                                'status' => 'valid',
                                'error_message' => null,
                            ];
                        } else {
                            $totalEmptyOrInvalid++;
                            $items[] = [
                                'sheet_name' => $currentSheetName,
                                'cell' => $cellCoordinate,
                                'system_id' => $parameter->monitored_system_id,
                                'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                'parameter_id' => $parameter->id,
                                'parameter_name' => $parameter->name,
                                'unit' => $parameter->unit,
                                'raw_value' => $rawValue,
                                'multiplier' => $multiplier,
                                'final_value' => null,
                                'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                'measured_date' => $dateTime->format('Y-m-d'),
                                'is_update' => false,
                                'existing_value' => null,
                                'status' => 'empty_or_invalid',
                                'error_message' => $isCellEmpty ? 'Célula vazia' : 'Conteúdo não numérico',
                            ];
                        }

                        continue;
                    }

                    $finalValue = round($numericValue * $multiplier, $parameter->decimals ?? 4);

                    // Check existing value
                    $existing = ParameterValue::query()
                        ->where('team_id', $team->id)
                        ->where('parameter_id', $parameter->id)
                        ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                        ->first();

                    $isUpdate = $existing !== null;
                    if ($isUpdate) {
                        $totalUpdates++;
                    } else {
                        $totalNew++;
                    }

                    $items[] = [
                        'sheet_name' => $currentSheetName,
                        'cell' => $cellCoordinate,
                        'system_id' => $parameter->monitored_system_id,
                        'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                        'parameter_id' => $parameter->id,
                        'parameter_name' => $parameter->name,
                        'unit' => $parameter->unit,
                        'raw_value' => $rawValue,
                        'multiplier' => $multiplier,
                        'final_value' => $finalValue,
                        'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                        'measured_date' => $dateTime->format('Y-m-d'),
                        'is_update' => $isUpdate,
                        'existing_value' => $existing?->value,
                        'status' => 'valid',
                        'error_message' => null,
                    ];
                }
            } elseif ($dateMode === 'horizontal_series') {
                $dateRow = (int) ($sheetConfig['date_row'] ?? 1);
                $timeRow = isset($sheetConfig['time_row']) && is_numeric($sheetConfig['time_row']) ? (int) $sheetConfig['time_row'] : null;
                $timeCell = strtoupper(trim((string) ($sheetConfig['time_cell'] ?? '')));
                $allowedTimes = isset($sheetConfig['allowed_times']) && is_array($sheetConfig['allowed_times']) ? $sheetConfig['allowed_times'] : null;
                $fallbackFixedTime = $timeCell && $sheet->cellExists($timeCell) ? $this->parseTimeValue($this->getEvaluatedCellValue($sheet->getCell($timeCell))) : null;
                $highestCol = $sheet->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);

                if ($isAllDates) {
                    for ($col = 1; $col <= $highestColIndex; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $cellVal = $this->getEvaluatedCellValue($sheet->getCell("{$colLetter}{$dateRow}"));
                        $colDate = $this->parseDateValue($cellVal);

                        if (! $colDate) {
                            continue;
                        }

                        $timeString = '00:00:00';
                        if ($timeRow && $sheet->cellExists("{$colLetter}{$timeRow}")) {
                            $rawTime = $this->getEvaluatedCellValue($sheet->getCell("{$colLetter}{$timeRow}"));
                            $parsedTime = $this->parseTimeValue($rawTime) ?? $this->extractTimeFromDateValue($rawTime);
                            if ($parsedTime) {
                                $timeString = $parsedTime;
                            }
                        }

                        if ($timeString === '00:00:00') {
                            $timeFromDate = $this->extractTimeFromDateValue($cellVal);
                            if ($timeFromDate) {
                                $timeString = $timeFromDate;
                            } elseif ($fallbackFixedTime) {
                                $timeString = $fallbackFixedTime;
                            }
                        }

                        if (! $this->isTimeAllowed($timeString, $allowedTimes)) {
                            continue;
                        }

                        $dateTime = $this->combineDateAndTime($colDate, $timeString);

                        foreach ($mappings as $mapping) {
                            $paramRow = isset($mapping['row']) && is_numeric($mapping['row']) ? (int) $mapping['row'] : null;
                            if (! $paramRow && ! empty($mapping['cell'])) {
                                [, $paramRow] = Coordinate::coordinateFromString((string) $mapping['cell']);
                            }

                            if (! $paramRow) {
                                continue;
                            }

                            $actualCoordinate = "{$colLetter}{$paramRow}";
                            $paramId = (int) ($mapping['parameter_id'] ?? 0);
                            $parameter = $parameters->get($paramId);

                            if (! $parameter) {
                                continue;
                            }

                            $multiplier = (float) ($mapping['multiplier'] ?? 1.0);
                            $rawValue = $this->getEvaluatedCellValue($sheet->getCell($actualCoordinate));
                            $numericValue = $this->normalizeNumericValue($rawValue);

                            if ($numericValue === null) {
                                $isCellEmpty = $this->isCellEmpty($rawValue);

                                if ($isCellEmpty && ! $ignoreEmptyCells) {
                                    $existing = ParameterValue::query()
                                        ->where('team_id', $team->id)
                                        ->where('parameter_id', $parameter->id)
                                        ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                        ->first();

                                    $isUpdate = $existing !== null;
                                    if ($isUpdate) {
                                        $totalUpdates++;
                                    } else {
                                        $totalNew++;
                                    }

                                    $items[] = [
                                        'sheet_name' => $currentSheetName,
                                        'cell' => $actualCoordinate,
                                        'system_id' => $parameter->monitored_system_id,
                                        'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                        'parameter_id' => $parameter->id,
                                        'parameter_name' => $parameter->name,
                                        'unit' => $parameter->unit,
                                        'raw_value' => $rawValue,
                                        'multiplier' => $multiplier,
                                        'final_value' => null,
                                        'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                        'measured_date' => $dateTime->format('Y-m-d'),
                                        'is_update' => $isUpdate,
                                        'existing_value' => $existing?->value,
                                        'status' => 'valid',
                                        'error_message' => null,
                                    ];
                                }

                                continue;
                            }

                            $finalValue = round($numericValue * $multiplier, $parameter->decimals ?? 4);

                            $existing = ParameterValue::query()
                                ->where('team_id', $team->id)
                                ->where('parameter_id', $parameter->id)
                                ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                ->first();

                            $isUpdate = $existing !== null;
                            if ($isUpdate) {
                                $totalUpdates++;
                            } else {
                                $totalNew++;
                            }

                            $items[] = [
                                'sheet_name' => $currentSheetName,
                                'cell' => $actualCoordinate,
                                'system_id' => $parameter->monitored_system_id,
                                'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                'parameter_id' => $parameter->id,
                                'parameter_name' => $parameter->name,
                                'unit' => $parameter->unit,
                                'raw_value' => $rawValue,
                                'multiplier' => $multiplier,
                                'final_value' => $finalValue,
                                'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                'measured_date' => $dateTime->format('Y-m-d'),
                                'is_update' => $isUpdate,
                                'existing_value' => $existing?->value,
                                'status' => 'valid',
                                'error_message' => null,
                            ];
                        }
                    }
                } else {
                    $targetDate = $referenceDate ? Carbon::parse($referenceDate) : Carbon::today();
                    $matchedCols = [];

                    for ($col = 1; $col <= $highestColIndex; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $cellVal = $this->getEvaluatedCellValue($sheet->getCell("{$colLetter}{$dateRow}"));
                        $parsedColDate = $this->parseDateValue($cellVal);

                        if ($parsedColDate && $parsedColDate->isSameDay($targetDate)) {
                            $matchedCols[] = [$colLetter, $cellVal, $parsedColDate];
                        }
                    }

                    if (empty($matchedCols)) {
                        $warnings[] = "Data {$targetDate->format('d/m/Y')} não foi encontrada na linha {$dateRow} da aba '{$currentSheetName}'.";

                        continue;
                    }

                    $columnsProcessed = 0;
                    foreach ($matchedCols as [$matchedCol, $matchedCellVal, $parsedColDate]) {
                        $timeString = '00:00:00';
                        if ($timeRow && $sheet->cellExists("{$matchedCol}{$timeRow}")) {
                            $rawTime = $this->getEvaluatedCellValue($sheet->getCell("{$matchedCol}{$timeRow}"));
                            $parsedTime = $this->parseTimeValue($rawTime) ?? $this->extractTimeFromDateValue($rawTime);
                            if ($parsedTime) {
                                $timeString = $parsedTime;
                            }
                        }

                        if ($timeString === '00:00:00') {
                            $timeFromDate = $this->extractTimeFromDateValue($matchedCellVal);
                            if ($timeFromDate) {
                                $timeString = $timeFromDate;
                            } elseif ($fallbackFixedTime) {
                                $timeString = $fallbackFixedTime;
                            }
                        }

                        if (! $this->isTimeAllowed($timeString, $allowedTimes)) {
                            continue;
                        }

                        $columnsProcessed++;
                        $dateTime = $this->combineDateAndTime($targetDate, $timeString);

                        foreach ($mappings as $mapping) {
                            $paramRow = isset($mapping['row']) && is_numeric($mapping['row']) ? (int) $mapping['row'] : null;
                            if (! $paramRow && ! empty($mapping['cell'])) {
                                [, $paramRow] = Coordinate::coordinateFromString((string) $mapping['cell']);
                            }

                            if (! $paramRow) {
                                continue;
                            }

                            $actualCoordinate = "{$matchedCol}{$paramRow}";
                            $paramId = (int) ($mapping['parameter_id'] ?? 0);
                            $parameter = $parameters->get($paramId);

                            if (! $parameter) {
                                continue;
                            }

                            $multiplier = (float) ($mapping['multiplier'] ?? 1.0);
                            $rawValue = $this->getEvaluatedCellValue($sheet->getCell($actualCoordinate));
                            $numericValue = $this->normalizeNumericValue($rawValue);

                            if ($numericValue === null) {
                                $isCellEmpty = $this->isCellEmpty($rawValue);

                                if ($isCellEmpty && ! $ignoreEmptyCells) {
                                    $existing = ParameterValue::query()
                                        ->where('team_id', $team->id)
                                        ->where('parameter_id', $parameter->id)
                                        ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                        ->first();

                                    $isUpdate = $existing !== null;
                                    if ($isUpdate) {
                                        $totalUpdates++;
                                    } else {
                                        $totalNew++;
                                    }

                                    $items[] = [
                                        'sheet_name' => $currentSheetName,
                                        'cell' => $actualCoordinate,
                                        'system_id' => $parameter->monitored_system_id,
                                        'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                        'parameter_id' => $parameter->id,
                                        'parameter_name' => $parameter->name,
                                        'unit' => $parameter->unit,
                                        'raw_value' => $rawValue,
                                        'multiplier' => $multiplier,
                                        'final_value' => null,
                                        'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                        'measured_date' => $dateTime->format('Y-m-d'),
                                        'is_update' => $isUpdate,
                                        'existing_value' => $existing?->value,
                                        'status' => 'valid',
                                        'error_message' => null,
                                    ];
                                } else {
                                    $totalEmptyOrInvalid++;
                                    $items[] = [
                                        'sheet_name' => $currentSheetName,
                                        'cell' => $actualCoordinate,
                                        'system_id' => $parameter->monitored_system_id,
                                        'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                        'parameter_id' => $parameter->id,
                                        'parameter_name' => $parameter->name,
                                        'unit' => $parameter->unit,
                                        'raw_value' => $rawValue,
                                        'multiplier' => $multiplier,
                                        'final_value' => null,
                                        'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                        'measured_date' => $dateTime->format('Y-m-d'),
                                        'is_update' => false,
                                        'existing_value' => null,
                                        'status' => 'empty_or_invalid',
                                        'error_message' => $isCellEmpty ? 'Célula vazia' : 'Conteúdo não numérico',
                                    ];
                                }

                                continue;
                            }

                            $finalValue = round($numericValue * $multiplier, $parameter->decimals ?? 4);

                            $existing = ParameterValue::query()
                                ->where('team_id', $team->id)
                                ->where('parameter_id', $parameter->id)
                                ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                ->first();

                            $isUpdate = $existing !== null;
                            if ($isUpdate) {
                                $totalUpdates++;
                            } else {
                                $totalNew++;
                            }

                            $items[] = [
                                'sheet_name' => $currentSheetName,
                                'cell' => $actualCoordinate,
                                'system_id' => $parameter->monitored_system_id,
                                'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                'parameter_id' => $parameter->id,
                                'parameter_name' => $parameter->name,
                                'unit' => $parameter->unit,
                                'raw_value' => $rawValue,
                                'multiplier' => $multiplier,
                                'final_value' => $finalValue,
                                'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                'measured_date' => $dateTime->format('Y-m-d'),
                                'is_update' => $isUpdate,
                                'existing_value' => $existing?->value,
                                'status' => 'valid',
                                'error_message' => null,
                            ];
                        }
                    }

                    if ($columnsProcessed === 0 && ! empty($allowedTimes)) {
                        $allowedStr = implode(', ', $allowedTimes);
                        $warnings[] = "A data {$targetDate->format('d/m/Y')} foi encontrada na aba '{$currentSheetName}', mas nenhum horário correspondeu aos horários permitidos ({$allowedStr}).";
                    }
                }
            } else {
                // Vertical series or legacy upload_date_match / all_dates_scan
                $dateCol = strtoupper(trim((string) ($sheetConfig['date_column'] ?? 'A')));
                $timeCol = isset($sheetConfig['time_column']) && ! empty($sheetConfig['time_column']) ? strtoupper(trim((string) $sheetConfig['time_column'])) : null;
                $timeCell = strtoupper(trim((string) ($sheetConfig['time_cell'] ?? '')));
                $allowedTimes = isset($sheetConfig['allowed_times']) && is_array($sheetConfig['allowed_times']) ? $sheetConfig['allowed_times'] : null;
                $fallbackFixedTime = $timeCell && $sheet->cellExists($timeCell) ? $this->parseTimeValue($this->getEvaluatedCellValue($sheet->getCell($timeCell))) : null;
                $highestRow = $sheet->getHighestRow();

                if ($isAllDates) {
                    for ($row = 1; $row <= $highestRow; $row++) {
                        $cellVal = $this->getEvaluatedCellValue($sheet->getCell("{$dateCol}{$row}"));
                        $rowDate = $this->parseDateValue($cellVal);

                        if (! $rowDate) {
                            continue;
                        }

                        $timeString = '00:00:00';
                        if ($timeCol && $sheet->cellExists("{$timeCol}{$row}")) {
                            $rawTime = $this->getEvaluatedCellValue($sheet->getCell("{$timeCol}{$row}"));
                            $parsedTime = $this->parseTimeValue($rawTime) ?? $this->extractTimeFromDateValue($rawTime);
                            if ($parsedTime) {
                                $timeString = $parsedTime;
                            }
                        }

                        if ($timeString === '00:00:00') {
                            $timeFromDate = $this->extractTimeFromDateValue($cellVal);
                            if ($timeFromDate) {
                                $timeString = $timeFromDate;
                            } elseif ($fallbackFixedTime) {
                                $timeString = $fallbackFixedTime;
                            }
                        }

                        if (! $this->isTimeAllowed($timeString, $allowedTimes)) {
                            continue;
                        }

                        $dateTime = $this->combineDateAndTime($rowDate, $timeString);

                        foreach ($mappings as $mapping) {
                            $paramCol = isset($mapping['column']) ? strtoupper(trim((string) $mapping['column'])) : null;
                            if (! $paramCol && ! empty($mapping['cell'])) {
                                [$paramCol] = Coordinate::coordinateFromString((string) $mapping['cell']);
                            }

                            if (! $paramCol) {
                                continue;
                            }

                            $actualCoordinate = "{$paramCol}{$row}";
                            $paramId = (int) ($mapping['parameter_id'] ?? 0);
                            $parameter = $parameters->get($paramId);

                            if (! $parameter) {
                                continue;
                            }

                            $multiplier = (float) ($mapping['multiplier'] ?? 1.0);
                            $rawValue = $this->getEvaluatedCellValue($sheet->getCell($actualCoordinate));
                            $numericValue = $this->normalizeNumericValue($rawValue);

                            if ($numericValue === null) {
                                $isCellEmpty = $this->isCellEmpty($rawValue);

                                if ($isCellEmpty && ! $ignoreEmptyCells) {
                                    $existing = ParameterValue::query()
                                        ->where('team_id', $team->id)
                                        ->where('parameter_id', $parameter->id)
                                        ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                        ->first();

                                    $isUpdate = $existing !== null;
                                    if ($isUpdate) {
                                        $totalUpdates++;
                                    } else {
                                        $totalNew++;
                                    }

                                    $items[] = [
                                        'sheet_name' => $currentSheetName,
                                        'cell' => $actualCoordinate,
                                        'system_id' => $parameter->monitored_system_id,
                                        'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                        'parameter_id' => $parameter->id,
                                        'parameter_name' => $parameter->name,
                                        'unit' => $parameter->unit,
                                        'raw_value' => $rawValue,
                                        'multiplier' => $multiplier,
                                        'final_value' => null,
                                        'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                        'measured_date' => $dateTime->format('Y-m-d'),
                                        'is_update' => $isUpdate,
                                        'existing_value' => $existing?->value,
                                        'status' => 'valid',
                                        'error_message' => null,
                                    ];
                                }

                                continue;
                            }

                            $finalValue = round($numericValue * $multiplier, $parameter->decimals ?? 4);

                            $existing = ParameterValue::query()
                                ->where('team_id', $team->id)
                                ->where('parameter_id', $parameter->id)
                                ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                ->first();

                            $isUpdate = $existing !== null;
                            if ($isUpdate) {
                                $totalUpdates++;
                            } else {
                                $totalNew++;
                            }

                            $items[] = [
                                'sheet_name' => $currentSheetName,
                                'cell' => $actualCoordinate,
                                'system_id' => $parameter->monitored_system_id,
                                'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                'parameter_id' => $parameter->id,
                                'parameter_name' => $parameter->name,
                                'unit' => $parameter->unit,
                                'raw_value' => $rawValue,
                                'multiplier' => $multiplier,
                                'final_value' => $finalValue,
                                'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                'measured_date' => $dateTime->format('Y-m-d'),
                                'is_update' => $isUpdate,
                                'existing_value' => $existing?->value,
                                'status' => 'valid',
                                'error_message' => null,
                            ];
                        }
                    }
                } else {
                    $targetDate = $referenceDate ? Carbon::parse($referenceDate) : Carbon::today();
                    $matchedRows = [];

                    for ($row = 1; $row <= $highestRow; $row++) {
                        $cellVal = $this->getEvaluatedCellValue($sheet->getCell("{$dateCol}{$row}"));
                        $parsedRowDate = $this->parseDateValue($cellVal);

                        if ($parsedRowDate && $parsedRowDate->isSameDay($targetDate)) {
                            $matchedRows[] = [$row, $cellVal, $parsedRowDate];
                        }
                    }

                    if (empty($matchedRows)) {
                        $warnings[] = "Data {$targetDate->format('d/m/Y')} não foi encontrada na coluna {$dateCol} da aba '{$currentSheetName}'.";

                        continue;
                    }

                    $rowsProcessed = 0;
                    foreach ($matchedRows as [$matchedRow, $matchedCellVal, $parsedRowDate]) {
                        $timeString = '00:00:00';
                        if ($timeCol && $sheet->cellExists("{$timeCol}{$matchedRow}")) {
                            $rawTime = $this->getEvaluatedCellValue($sheet->getCell("{$timeCol}{$matchedRow}"));
                            $parsedTime = $this->parseTimeValue($rawTime) ?? $this->extractTimeFromDateValue($rawTime);
                            if ($parsedTime) {
                                $timeString = $parsedTime;
                            }
                        }

                        if ($timeString === '00:00:00') {
                            $timeFromDate = $this->extractTimeFromDateValue($matchedCellVal);
                            if ($timeFromDate) {
                                $timeString = $timeFromDate;
                            } elseif ($fallbackFixedTime) {
                                $timeString = $fallbackFixedTime;
                            }
                        }

                        if (! $this->isTimeAllowed($timeString, $allowedTimes)) {
                            continue;
                        }

                        $rowsProcessed++;
                        $dateTime = $this->combineDateAndTime($targetDate, $timeString);

                        foreach ($mappings as $mapping) {
                            $paramCol = isset($mapping['column']) ? strtoupper(trim((string) $mapping['column'])) : null;
                            if (! $paramCol && ! empty($mapping['cell'])) {
                                [$paramCol] = Coordinate::coordinateFromString((string) $mapping['cell']);
                            }

                            if (! $paramCol) {
                                continue;
                            }

                            $actualCoordinate = "{$paramCol}{$matchedRow}";
                            $paramId = (int) ($mapping['parameter_id'] ?? 0);
                            $parameter = $parameters->get($paramId);

                            if (! $parameter) {
                                continue;
                            }

                            $multiplier = (float) ($mapping['multiplier'] ?? 1.0);
                            $rawValue = $this->getEvaluatedCellValue($sheet->getCell($actualCoordinate));
                            $numericValue = $this->normalizeNumericValue($rawValue);

                            if ($numericValue === null) {
                                $isCellEmpty = $this->isCellEmpty($rawValue);

                                if ($isCellEmpty && ! $ignoreEmptyCells) {
                                    $existing = ParameterValue::query()
                                        ->where('team_id', $team->id)
                                        ->where('parameter_id', $parameter->id)
                                        ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                        ->first();

                                    $isUpdate = $existing !== null;
                                    if ($isUpdate) {
                                        $totalUpdates++;
                                    } else {
                                        $totalNew++;
                                    }

                                    $items[] = [
                                        'sheet_name' => $currentSheetName,
                                        'cell' => $actualCoordinate,
                                        'system_id' => $parameter->monitored_system_id,
                                        'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                        'parameter_id' => $parameter->id,
                                        'parameter_name' => $parameter->name,
                                        'unit' => $parameter->unit,
                                        'raw_value' => $rawValue,
                                        'multiplier' => $multiplier,
                                        'final_value' => null,
                                        'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                        'measured_date' => $dateTime->format('Y-m-d'),
                                        'is_update' => $isUpdate,
                                        'existing_value' => $existing?->value,
                                        'status' => 'valid',
                                        'error_message' => null,
                                    ];
                                } else {
                                    $totalEmptyOrInvalid++;
                                    $items[] = [
                                        'sheet_name' => $currentSheetName,
                                        'cell' => $actualCoordinate,
                                        'system_id' => $parameter->monitored_system_id,
                                        'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                        'parameter_id' => $parameter->id,
                                        'parameter_name' => $parameter->name,
                                        'unit' => $parameter->unit,
                                        'raw_value' => $rawValue,
                                        'multiplier' => $multiplier,
                                        'final_value' => null,
                                        'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                        'measured_date' => $dateTime->format('Y-m-d'),
                                        'is_update' => false,
                                        'existing_value' => null,
                                        'status' => 'empty_or_invalid',
                                        'error_message' => $isCellEmpty ? 'Célula vazia' : 'Conteúdo não numérico',
                                    ];
                                }

                                continue;
                            }

                            $finalValue = round($numericValue * $multiplier, $parameter->decimals ?? 4);

                            $existing = ParameterValue::query()
                                ->where('team_id', $team->id)
                                ->where('parameter_id', $parameter->id)
                                ->where('measured_at', $dateTime->format('Y-m-d H:i:s'))
                                ->first();

                            $isUpdate = $existing !== null;
                            if ($isUpdate) {
                                $totalUpdates++;
                            } else {
                                $totalNew++;
                            }

                            $items[] = [
                                'sheet_name' => $currentSheetName,
                                'cell' => $actualCoordinate,
                                'system_id' => $parameter->monitored_system_id,
                                'system_name' => $parameter->monitoredSystem?->name ?? 'Sistema',
                                'parameter_id' => $parameter->id,
                                'parameter_name' => $parameter->name,
                                'unit' => $parameter->unit,
                                'raw_value' => $rawValue,
                                'multiplier' => $multiplier,
                                'final_value' => $finalValue,
                                'measured_at' => $dateTime->format('Y-m-d H:i:s'),
                                'measured_date' => $dateTime->format('Y-m-d'),
                                'is_update' => $isUpdate,
                                'existing_value' => $existing?->value,
                                'status' => 'valid',
                                'error_message' => null,
                            ];
                        }
                    }

                    if ($rowsProcessed === 0 && ! empty($allowedTimes)) {
                        $allowedStr = implode(', ', $allowedTimes);
                        $warnings[] = "A data {$targetDate->format('d/m/Y')} foi encontrada na aba '{$currentSheetName}', mas nenhum horário correspondeu aos horários permitidos ({$allowedStr}).";
                    }
                }
            }
        }

        return [
            'total_extracted' => count($items),
            'total_new' => $totalNew,
            'total_updates' => $totalUpdates,
            'total_empty_or_invalid' => $totalEmptyOrInvalid,
            'items' => $items,
            'warnings' => $warnings,
            'sheets_found' => $sheetNames,
        ];
    }

    /**
     * @param  array<string, mixed>  $sheetConfig
     */
    protected function resolveWorksheet(Spreadsheet $spreadsheet, array $sheetConfig, int $sheetIndex): ?Worksheet
    {
        $idType = $sheetConfig['sheet_identifier_type'] ?? 'index';

        if ($idType === 'name' && ! empty($sheetConfig['sheet_name'])) {
            $sheet = $spreadsheet->getSheetByName((string) $sheetConfig['sheet_name']);
            if ($sheet) {
                return $sheet;
            }
        }

        $idx = isset($sheetConfig['sheet_identifier_value']) && is_numeric($sheetConfig['sheet_identifier_value'])
            ? (int) $sheetConfig['sheet_identifier_value'] - 1
            : $sheetIndex;

        try {
            return $spreadsheet->getSheet($idx);
        } catch (Exception) {
            return null;
        }
    }

    protected function extractDateTimeFromCells(Worksheet $sheet, string $dateCell, string $timeCell, ?string $fallbackDate): Carbon
    {
        $date = null;
        if (! empty($dateCell) && $sheet->cellExists($dateCell)) {
            $rawDate = $this->getEvaluatedCellValue($sheet->getCell($dateCell));
            $date = $this->parseDateValue($rawDate);
        }

        if (! $date) {
            $date = $fallbackDate ? Carbon::parse($fallbackDate) : Carbon::today();
        }

        $timeString = '00:00:00';
        if (! empty($timeCell) && $sheet->cellExists($timeCell)) {
            $rawTime = $this->getEvaluatedCellValue($sheet->getCell($timeCell));
            $parsedTime = $this->parseTimeValue($rawTime) ?? $this->extractTimeFromDateValue($rawTime);
            if ($parsedTime) {
                $timeString = $parsedTime;
            }
        }

        return $this->combineDateAndTime($date, $timeString);
    }

    protected function parseDateValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                ExcelDate::roundMicroseconds($dt);

                return Carbon::createFromFormat('Y-m-d', $dt->format('Y-m-d'))->startOfDay();
            } catch (Exception) {
                // Not a valid excel serial date
            }
        }

        if (is_string($value)) {
            $value = trim($value);
            $formats = [
                'd/m/Y H:i:s',
                'd/m/Y H:i',
                'd/m/Y',
                'd-m-Y H:i:s',
                'd-m-Y H:i',
                'd-m-Y',
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'Y-m-d',
                'd/m/y H:i:s',
                'd/m/y H:i',
                'd/m/y',
                'Y/m/d H:i:s',
                'Y/m/d H:i',
                'Y/m/d',
            ];
            foreach ($formats as $fmt) {
                try {
                    return Carbon::createFromFormat($fmt, $value)->startOfDay();
                } catch (Exception) {
                    continue;
                }
            }

            try {
                return Carbon::parse($value)->startOfDay();
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }

    protected function parseTimeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $floatVal = (float) $value;
            $fraction = $floatVal - floor($floatVal);
            $totalSeconds = (int) round($fraction * 86400);
            if ($totalSeconds >= 86400) {
                $totalSeconds = 0;
            }

            $sec = $totalSeconds % 60;
            if ($sec === 59 || $sec === 1) {
                $totalSeconds = (int) round($totalSeconds / 60) * 60;
                if ($totalSeconds >= 86400) {
                    $totalSeconds = 0;
                }
            }

            $hours = intdiv($totalSeconds, 3600) % 24;
            $minutes = intdiv($totalSeconds % 3600, 60);
            $seconds = $totalSeconds % 60;

            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
                $h = (int) $matches[1];
                $m = (int) $matches[2];
                $s = isset($matches[3]) ? (int) $matches[3] : 0;

                if ($s === 59) {
                    $m++;
                    $s = 0;
                    if ($m >= 60) {
                        $m = 0;
                        $h = ($h + 1) % 24;
                    }
                } elseif ($s === 1) {
                    $s = 0;
                }

                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }
        }

        return null;
    }

    protected function extractTimeFromDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                ExcelDate::roundMicroseconds($dt);
                $carbon = Carbon::instance($dt);
                $sec = (int) $carbon->format('s');
                if ($sec === 59 || $sec === 1) {
                    $carbon = $carbon->roundMinute();
                }
                $time = $carbon->format('H:i:s');
                if ($time !== '00:00:00') {
                    return $time;
                }
            } catch (Exception) {
                // Not a valid excel serial date
            }
        }

        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\b/', $value, $matches)) {
                $h = (int) $matches[1];
                $m = (int) $matches[2];
                $s = isset($matches[3]) ? (int) $matches[3] : 0;

                if ($s === 59) {
                    $m++;
                    $s = 0;
                    if ($m >= 60) {
                        $m = 0;
                        $h = ($h + 1) % 24;
                    }
                } elseif ($s === 1) {
                    $s = 0;
                }

                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }
        }

        return null;
    }

    protected function combineDateAndTime(Carbon $date, string $timeString): Carbon
    {
        $parts = explode(':', $timeString);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);

        if ($s === 59) {
            $m++;
            $s = 0;
            if ($m >= 60) {
                $m = 0;
                $h = ($h + 1) % 24;
            }
        } elseif ($s === 1) {
            $s = 0;
        }

        return $date->copy()->setTime($h, $m, $s);
    }

    protected function normalizeNumericValue(mixed $val): ?float
    {
        if ($val === null || $val === '' || $val === '-' || $val === 'N/A' || $val === 'n/a') {
            return null;
        }

        if (is_numeric($val)) {
            return (float) $val;
        }

        if (is_string($val)) {
            $clean = trim($val);
            // Handle Brazilian number formatting: 1.234,56 -> 1234.56
            if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $clean)) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } elseif (str_contains($clean, ',') && ! str_contains($clean, '.')) {
                $clean = str_replace(',', '.', $clean);
            }

            if (is_numeric($clean)) {
                return (float) $clean;
            }
        }

        return null;
    }

    protected function getEvaluatedCellValue(Cell $cell): mixed
    {
        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable) {
            return $cell->getValue();
        }
    }

    /**
     * Verify if a parsed time string matches allowed times configured for the sheet.
     *
     * @param  string[]|null  $allowedTimes
     */
    protected function isTimeAllowed(?string $timeString, ?array $allowedTimes): bool
    {
        if (empty($allowedTimes)) {
            return true;
        }

        if ($timeString === null || $timeString === '') {
            return false;
        }

        $normalizedTime = substr($timeString, 0, 5);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $timeString, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $sec = isset($m[3]) ? (int) $m[3] : 0;
            if ($sec === 59) {
                $min++;
                if ($min >= 60) {
                    $min = 0;
                    $h = ($h + 1) % 24;
                }
            }
            $normalizedTime = sprintf('%02d:%02d', $h, $min);
        }

        foreach ($allowedTimes as $allowed) {
            $allowed = trim((string) $allowed);
            if ($allowed === '') {
                continue;
            }
            if (preg_match('/^(\d{1,2}):(\d{2})/', $allowed, $m)) {
                $normalizedAllowed = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
                if ($normalizedAllowed === $normalizedTime) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether empty spreadsheet cells should be ignored or imported as null.
     *
     * @param  array<string, mixed>  $sheetConfig
     * @param  array<string, mixed>  $templateConfig
     */
    protected function shouldIgnoreEmptyCells(array $sheetConfig, array $templateConfig): bool
    {
        if (isset($sheetConfig['ignore_empty_cells'])) {
            return (bool) $sheetConfig['ignore_empty_cells'];
        }

        if (isset($sheetConfig['empty_values_mode'])) {
            return $sheetConfig['empty_values_mode'] !== 'save_empty';
        }

        if (isset($templateConfig['ignore_empty_cells'])) {
            return (bool) $templateConfig['ignore_empty_cells'];
        }

        if (isset($templateConfig['empty_values_mode'])) {
            return $templateConfig['empty_values_mode'] !== 'save_empty';
        }

        return true;
    }

    /**
     * Check if a cell raw value represents an empty/blank value rather than invalid text.
     */
    protected function isCellEmpty(mixed $val): bool
    {
        if ($val === null) {
            return true;
        }

        if (is_string($val)) {
            $trimmed = trim($val);

            return $trimmed === '' || in_array(strtoupper($trimmed), ['-', 'N/A', '#N/A', 'NULL', 'VAZIO'], true);
        }

        return false;
    }
}
