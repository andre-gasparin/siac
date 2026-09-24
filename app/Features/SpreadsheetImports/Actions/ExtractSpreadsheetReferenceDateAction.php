<?php

namespace App\Features\SpreadsheetImports\Actions;

use Carbon\Carbon;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExtractSpreadsheetReferenceDateAction
{
    /**
     * Attempt to extract a reference date from filename, subject, body, or spreadsheet content.
     */
    public function execute(
        ?string $fileName = null,
        ?string $subject = null,
        ?string $body = null,
        ?string $filePath = null,
        ?string $customPattern = null,
    ): ?string {
        // 1. If custom pattern provided, test against all text sources
        if ($customPattern) {
            $allText = "{$fileName} {$subject} {$body}";
            if (@preg_match($customPattern, $allText, $matches) && isset($matches[1])) {
                $parsed = $this->tryParseDateString($matches[1]);
                if ($parsed) {
                    return $parsed;
                }
            }
        }

        // 2. Try filename first (often contains dates like Relatorio_2026-08-31.xlsx or 31_08_2026.csv)
        if ($fileName) {
            $date = $this->extractFromText($fileName);
            if ($date) {
                return $date;
            }
        }

        // 3. Try email subject
        if ($subject) {
            $date = $this->extractFromText($subject);
            if ($date) {
                return $date;
            }
        }

        // 4. Try email body
        if ($body) {
            $date = $this->extractFromText($body);
            if ($date) {
                return $date;
            }
        }

        // 5. If filePath is provided and exists, try inspecting spreadsheet cells for dates
        if ($filePath && file_exists($filePath)) {
            $date = $this->extractFromSpreadsheetFile($filePath);
            if ($date) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Look for date patterns in a text string.
     */
    protected function extractFromText(string $text): ?string
    {
        // ISO format: YYYY-MM-DD or YYYY_MM_DD or YYYY.MM.DD
        if (preg_match('/\b(20\d{2})[-_\.](0[1-9]|1[0-2])[-_\.](0[1-9]|[12]\d|3[01])\b/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Brazilian/European format: DD-MM-YYYY or DD_MM_YYYY or DD.MM.YYYY or DD/MM/YYYY
        if (preg_match('/\b(0[1-9]|[12]\d|3[01])[\/_\.-](0[1-9]|1[0-2])[\/_\.-](20\d{2})\b/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Numeric compact: YYYYMMDD (e.g. 20260831)
        if (preg_match('/\b(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\b/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Numeric compact: DDMMYYYY (e.g. 31082026)
        if (preg_match('/\b(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(20\d{2})\b/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Month and Year: e.g. "Agosto_2026", "Setembro de 2026", "Jan-2026"
        $monthMap = [
            'jan' => 1, 'janeiro' => 1,
            'fev' => 2, 'fevereiro' => 2,
            'mar' => 3, 'marco' => 3, 'março' => 3,
            'abr' => 4, 'abril' => 4,
            'mai' => 5, 'maio' => 5,
            'jun' => 6, 'junho' => 6,
            'jul' => 7, 'julho' => 7,
            'ago' => 8, 'agosto' => 8,
            'set' => 9, 'setembro' => 9,
            'out' => 10, 'outubro' => 10,
            'nov' => 11, 'novembro' => 11,
            'dez' => 12, 'dezembro' => 12,
        ];

        $monthsRegex = implode('|', array_keys($monthMap));
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:(?:dia\s*)?(0[1-9]|[12]\d|3[01])[\s_\.\-\/]+(?:de\s*)?)?('.$monthsRegex.')[\s_\.\-\/]+(?:de\s*)?(20\d{2})/iu', $text, $m)) {
            $day = ! empty($m[1]) ? (int) $m[1] : 1;
            $monthName = mb_strtolower($m[2], 'UTF-8');
            $month = $monthMap[$monthName] ?? 1;
            $year = (int) $m[3];

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return null;
    }

    /**
     * Inspect spreadsheet sample rows/headers for latest date.
     */
    protected function extractFromSpreadsheetFile(string $filePath): ?string
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = min($worksheet->getHighestRow(), 100);
            $highestCol = min(Coordinate::columnIndexFromString($worksheet->getHighestColumn()), 30);

            $latestDate = null;

            for ($row = 1; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= $highestCol; $col++) {
                    $cell = $worksheet->getCell([$col, $row]);
                    $val = $cell->getValue();
                    if ($val === null || $val === '') {
                        continue;
                    }

                    if (is_numeric($val) && $val > 40000 && $val < 60000) {
                        try {
                            $excelDate = Date::excelToDateTimeObject($val);
                            $formatted = $excelDate->format('Y-m-d');
                            if (! $latestDate || $formatted > $latestDate) {
                                $latestDate = $formatted;
                            }
                        } catch (Exception) {
                        }
                    } elseif (is_string($val)) {
                        $parsed = $this->extractFromText($val);
                        if ($parsed && (! $latestDate || $parsed > $latestDate)) {
                            $latestDate = $parsed;
                        }
                    }
                }
            }

            return $latestDate;
        } catch (Exception) {
            return null;
        }
    }

    protected function tryParseDateString(string $dateStr): ?string
    {
        try {
            return Carbon::parse(trim($dateStr))->format('Y-m-d');
        } catch (Exception) {
            return $this->extractFromText($dateStr);
        }
    }
}
