<?php

declare(strict_types=1);

namespace App\Support\Export;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A list, as a file. One entry point for every list surface: hand it the
 * column labels and an iterable of rows and it writes the download.
 *
 * The rows arrive as an iterable, never an array, so the caller can hand over
 * a generator and the whole result set is never resident. CSV honours that all
 * the way to the socket. XLSX and PDF cannot: both formats need the document
 * finished before the first byte is meaningful, so they are capped lower.
 *
 * Nothing here knows what a person or a post is. The list's own controller
 * turns models into scalar rows; this turns scalar rows into bytes.
 */
final class ListExport
{
    /**
     * The most rows any export will carry. Beyond this a list is a data dump
     * and belongs in a queued job with a signed link, not in a request.
     */
    public const MAX_ROWS = 25_000;

    /** Excel reads a UTF-8 CSV as Latin-1 without it, so umlauts arrive broken. */
    private const BOM = "\u{FEFF}";

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     * @param  array<string, string>  $headers  Extra response headers, e.g. the truncation flag.
     */
    public static function stream(
        ExportFormat $format,
        string $basename,
        string $title,
        array $headings,
        iterable $rows,
        array $headers = [],
    ): StreamedResponse {
        $filename = $basename.'-'.now()->format('Y-m-d').'.'.$format->value;

        $writer = match ($format) {
            ExportFormat::Csv => fn (): null => self::writeCsv($headings, $rows),
            ExportFormat::Xlsx => fn (): null => self::writeXlsx($title, $headings, $rows),
            ExportFormat::Pdf => fn (): null => self::writePdf($title, $headings, $rows),
        };

        return response()->streamDownload($writer, $filename, [
            'Content-Type' => $format->contentType(),
            'X-Export-Row-Limit' => (string) $format->rowLimit(),
            ...$headers,
        ]);
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    private static function writeCsv(array $headings, iterable $rows): null
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            return null;
        }

        fwrite($handle, self::BOM);
        fputcsv($handle, $headings, escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }

        fclose($handle);

        return null;
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    private static function writeXlsx(string $title, array $headings, iterable $rows): null
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        // Excel rejects a sheet name over 31 characters or carrying []:*?/\.
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title) ?? 'Export', 0, 31));

        $sheet->fromArray($headings, null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);

        $line = 2;

        foreach ($rows as $row) {
            // Set cells explicitly: a value starting with `=`, `+` or `-` is a
            // formula to Excel, and a spreadsheet built from user-supplied text
            // must not execute it.
            foreach ($row as $index => $value) {
                $sheet->setCellValueExplicit(
                    [$index + 1, $line],
                    (string) $value,
                    DataType::TYPE_STRING,
                );
            }

            $line++;
        }

        foreach (range(1, count($headings)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        (new Xlsx($spreadsheet))->save('php://output');
        $spreadsheet->disconnectWorksheets();

        return null;
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    private static function writePdf(string $title, array $headings, iterable $rows): null
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(View::make('exports.table', [
            'title' => $title,
            'headings' => $headings,
            'rows' => iterator_to_array($rows, false),
            'printedAt' => now()->toDayDateTimeString(),
        ])->render());

        $dompdf->render();

        echo $dompdf->output();

        return null;
    }
}
