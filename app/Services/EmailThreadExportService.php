<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class EmailThreadExportService
{
    private const CSV_HEADERS = [
        'Subject',
        'Contact',
        'Conversation ID',
        'Conversation status',
        'Direction',
        'Date',
        'Delivery status',
        'Message',
        'Attachments',
    ];

    /**
     * @param array<int, string> $textLines
     * @param array<int, array<int, string|null>> $csvRows
     */
    public function download(string $format, string $filenameBase, array $textLines, array $csvRows)
    {
        $format = strtolower($format);

        [$extension, $contentType, $content] = match ($format) {
            'txt' => ['txt', 'text/plain; charset=UTF-8', implode("\n", $textLines)],
            'pdf' => ['pdf', 'application/pdf', $this->pdf($textLines)],
            'csv' => ['csv', 'text/csv; charset=UTF-8', $this->csv($csvRows)],
            'docx' => [
                'docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                $this->docx($textLines),
            ],
            default => throw new RuntimeException('Unsupported email thread export format.'),
        };

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filenameBase.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @param array<int, array<int, string|null>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the CSV export.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::CSV_HEADERS, ',', '"', '\\');
        foreach ($rows as $row) {
            $safeRow = array_map(fn ($value): string => $this->safeCsvField($value), $row);
            fputcsv($stream, $safeRow, ',', '"', '\\');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            throw new RuntimeException('Unable to read the CSV export.');
        }

        return $content;
    }

    /** @param array<int, string> $lines */
    private function pdf(array $lines): string
    {
        $pdfLines = [];
        foreach ($lines as $line) {
            foreach (explode("\n", str_replace("\r", '', $line)) as $part) {
                $converted = function_exists('iconv')
                    ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $part)
                    : false;
                $ascii = $converted === false
                    ? (preg_replace('/[^\x20-\x7E]/', '?', $part) ?? '')
                    : $converted;
                $ascii = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $ascii) ?? '';
                $wrapped = wordwrap($ascii, 95, "\n", true);
                array_push($pdfLines, ...explode("\n", $wrapped));
            }
        }

        $linePages = array_chunk($pdfLines ?: [''], 50);
        $pageIds = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        foreach ($linePages as $index => $pageLines) {
            $pageId = 3 + ($index * 2);
            $contentId = $pageId + 1;
            $pageIds[] = $pageId.' 0 R';

            $stream = "BT\n/F1 10 Tf\n50 790 Td\n";
            foreach ($pageLines as $pageLine) {
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $pageLine);
                $stream .= '('.$escaped.") Tj\n0 -14 Td\n";
            }
            $stream .= 'ET';

            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 '.(3 + (count($linePages) * 2)).' 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $fontId = 3 + (count($linePages) * 2);
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pageIds).' >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $document = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $objectId => $object) {
            $offsets[$objectId] = strlen($document);
            $document .= $objectId." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($document);
        $objectCount = max(array_keys($objects)) + 1;
        $document .= "xref\n0 ".$objectCount."\n0000000000 65535 f \n";
        for ($objectId = 1; $objectId < $objectCount; $objectId++) {
            $document .= sprintf("%010d 00000 n \n", $offsets[$objectId]);
        }
        $document .= "trailer\n<< /Size ".$objectCount." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $document;
    }

    /** @param array<int, string> $lines */
    private function docx(array $lines): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZIP extension is required to create a Word document.');
        }

        $path = tempnam(sys_get_temp_dir(), 'email-thread-');
        if ($path === false) {
            throw new RuntimeException('Unable to create the Word export.');
        }

        try {
            $archive = new ZipArchive();
            if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the Word export.');
            }

            $archive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'</Types>');
            $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                .'</Relationships>');

            $paragraphs = implode('', array_map(fn (string $line): string => $this->wordParagraph($line), $lines));
            $archive->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                .'<w:body>'.$paragraphs.'<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
                .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
                .'</w:body></w:document>');

            $archive->close();
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Unable to read the Word export.');
            }

            return $content;
        } finally {
            @unlink($path);
        }
    }

    private function wordParagraph(string $line): string
    {
        $xml = '<w:p>';
        foreach (explode("\n", str_replace("\r", '', $line)) as $index => $part) {
            if ($index > 0) {
                $xml .= '<w:r><w:br/></w:r>';
            }
            if ($part !== '') {
                $part = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $part) ?? '';
                $xml .= '<w:r><w:t xml:space="preserve">'.htmlspecialchars($part, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r>';
            }
        }

        return $xml.'</w:p>';
    }

    private function safeCsvField(mixed $value): string
    {
        $field = (string) ($value ?? '');

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $field) === 1 ? "'".$field : $field;
    }
}
