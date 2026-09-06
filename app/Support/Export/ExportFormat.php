<?php

declare(strict_types=1);

namespace App\Support\Export;

/**
 * The file a list export can be asked for. The value is what travels in the
 * `format` query parameter, and it is also the file extension, so there is no
 * second mapping to keep in step.
 */
enum ExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }

    /**
     * How many rows this format is allowed to carry.
     *
     * CSV is written row by row and costs nothing to hold, so it gets the full
     * cap. XLSX builds the whole sheet in memory before a byte is written and
     * PDF lays every row out, so both stop earlier.
     */
    public function rowLimit(): int
    {
        return match ($this) {
            self::Csv => ListExport::MAX_ROWS,
            self::Xlsx => 10_000,
            self::Pdf => 2_000,
        };
    }
}
