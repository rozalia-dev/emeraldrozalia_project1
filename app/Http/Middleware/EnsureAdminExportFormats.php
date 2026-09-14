<?php

namespace App\Http\Middleware;

use App\Services\TabularExportService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EnsureAdminExportFormats
{
    public function __construct(private readonly TabularExportService $exports)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $isAdmin = $request->is('admin/*');
        $isExport = $isAdmin && $request->isMethod('GET') && $this->isExportPath($request->path());
        $requestedFormat = strtolower(trim((string) $request->query('format', '')));

        if ($isExport && $requestedFormat === 'pdf') {
            // Existing export controllers already own authorization, filtering and
            // live-data selection. Ask them for their canonical CSV stream, then
            // render the same rows as a real PDF so business logic stays unified.
            $request->query->set('format', 'csv');
            $response = $next($request);

            return $this->convertCsvResponseToPdf($request, $response);
        }

        $response = $next($request);

        if ($isAdmin) {
            $this->injectExportAssets($response);
        }

        return $response;
    }

    private function isExportPath(string $path): bool
    {
        return in_array('export', array_values(array_filter(explode('/', trim($path, '/')))), true);
    }

    private function convertCsvResponseToPdf(Request $request, Response $response): Response
    {
        if ($response->isRedirection() || $response->getStatusCode() >= 400) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        $disposition = (string) $response->headers->get('Content-Disposition', '');
        $looksLikeCsv = str_contains($contentType, 'csv') || preg_match('/\.csv(?:"|;|$)/i', $disposition) === 1;

        // If a future export controller natively returns PDF, preserve it.
        if (str_contains($contentType, 'application/pdf')) {
            return $response;
        }

        if (! $looksLikeCsv) {
            return $response;
        }

        $csv = $this->responseContent($response);
        $sourceFilename = $this->filenameFromDisposition($disposition)
            ?: Str($request->path())->replace('/', '-')->append('-'.now()->format('Ymd-His').'.csv')->toString();
        $pdfFilename = preg_replace('/\.csv$/i', '.pdf', $sourceFilename) ?: 'export-'.now()->format('Ymd-His').'.pdf';
        $title = str($pdfFilename)
            ->beforeLast('.')
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();

        return $this->exports->pdfFromCsv($csv, $pdfFilename, $title);
    }

    private function responseContent(Response $response): string
    {
        if (! $response instanceof StreamedResponse) {
            return (string) $response->getContent();
        }

        $callback = $response->getCallback();
        if (! is_callable($callback)) {
            return '';
        }

        ob_start();
        try {
            $callback();

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    private function filenameFromDisposition(string $disposition): ?string
    {
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $matches) !== 1) {
            return null;
        }

        return rawurldecode(trim($matches[1]));
    }

    private function injectExportAssets(Response $response): void
    {
        if ($response instanceof StreamedResponse) {
            return;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = (string) $response->getContent();
        if ($content === '' || ! str_contains($content, '</body>')) {
            return;
        }

        if (! str_contains($content, '/css/admin-export-formats.css')) {
            $content = str_replace(
                '</head>',
                '<link rel="stylesheet" href="/css/admin-export-formats.css?v=20260914-1">'."\n".'</head>',
                $content,
            );
        }

        if (! str_contains($content, '/js/admin-export-formats.js')) {
            $content = str_replace(
                '</body>',
                '<script src="/js/admin-export-formats.js?v=20260914-1"></script>'."\n".'</body>',
                $content,
            );
        }

        $response->setContent($content);
        $response->headers->remove('Content-Length');
    }
}
