<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TabularExportService
{
    public function pdfFromCsv(string $csv, string $filename, string $title): Response
    {
        $rows = $this->parseCsv($csv);
        $pdf = $this->buildPdf($title, $rows);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->safeFilename($filename).'"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];

        while (($row = fgetcsv($stream)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
        }

        fclose($stream);

        return $rows;
    }

    private function buildPdf(string $title, array $rows): string
    {
        $lines = $this->documentLines($title, $rows);
        $pages = array_chunk($lines, 54);
        if ($pages === []) {
            $pages = [['No export rows were returned.']];
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        $pageIds = [];
        foreach ($pages as $index => $pageLines) {
            $pageId = 4 + ($index * 2);
            $contentId = $pageId + 1;
            $pageIds[] = $pageId.' 0 R';
            $stream = $this->pageStream($pageLines, $index === 0);
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pages).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        $maxObject = max(array_keys($objects));

        for ($id = 1; $id <= $maxObject; $id++) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".($objects[$id] ?? '<< >>')."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxObject; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }
        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function documentLines(string $title, array $rows): array
    {
        $lines = [
            $this->ascii($title),
            'Generated: '.now()->format('d M Y H:i:s T'),
            '',
        ];

        if ($rows === []) {
            $lines[] = 'No export rows were returned.';

            return $lines;
        }

        $headers = array_map(fn ($value): string => $this->ascii((string) $value), array_shift($rows));
        if ($rows === []) {
            $lines[] = 'No data rows were returned.';

            return $lines;
        }

        foreach ($rows as $index => $row) {
            $lines[] = 'Record '.($index + 1);
            foreach ($headers as $column => $header) {
                $label = $header !== '' ? $header : 'Column '.($column + 1);
                $value = $this->ascii((string) ($row[$column] ?? ''));
                $wrapped = explode("\n", wordwrap($label.': '.$value, 88, "\n", true));
                foreach ($wrapped as $wrappedLine) {
                    $lines[] = $wrappedLine;
                }
            }
            $lines[] = str_repeat('-', 88);
        }

        return $lines;
    }

    private function pageStream(array $lines, bool $firstPage): string
    {
        $stream = "BT\n";
        foreach ($lines as $index => $line) {
            $fontSize = $firstPage && $index === 0 ? 13 : 9;
            $y = 806 - ($index * 14);
            $stream .= '/F1 '.$fontSize." Tf\n";
            $stream .= '1 0 0 1 36 '.$y." Tm\n";
            $stream .= '('.$this->escapePdfText($line).") Tj\n";
        }
        $stream .= "ET";

        return $stream;
    }

    private function ascii(string $value): string
    {
        $value = str_replace(
            ['€', '£', '–', '—', '“', '”', '‘', '’', "\r", "\n", "\t"],
            ['EUR ', 'GBP ', '-', '-', '"', '"', "'", "'", ' ', ' ', ' '],
            $value,
        );
        $value = Str::ascii($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim(preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value);
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii($value));
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'export.pdf';
        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename = preg_replace('/\.[^.]+$/', '', $filename) ?: 'export';
            $filename .= '.pdf';
        }

        return $filename;
    }
}
