<?php

namespace App\Http\Controllers;

use App\Models\ProductCatalogue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCatalogueController extends Controller
{
    public function show()
    {
        $catalogue = $this->publishedCatalogue();

        return view('site.product-catalogue', compact('catalogue'));
    }

    public function download(): StreamedResponse
    {
        $catalogue = $this->publishedCatalogue();
        abort_unless($catalogue->pdf_path && Storage::disk('local')->exists($catalogue->pdf_path), 404);

        ProductCatalogue::withoutGlobalScopes()->whereKey($catalogue->id)->increment('download_count');

        return Storage::disk('local')->download(
            $catalogue->pdf_path,
            $this->downloadName($catalogue),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function cover(): BinaryFileResponse
    {
        $catalogue = $this->publishedCatalogue();
        abort_unless($catalogue->cover_path && Storage::disk('local')->exists($catalogue->cover_path), 404);

        return response()->file(Storage::disk('local')->path($catalogue->cover_path), [
            'Content-Type' => Storage::disk('local')->mimeType($catalogue->cover_path) ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function publishedCatalogue(): ProductCatalogue
    {
        $catalogue = ProductCatalogue::publishedForCurrentCompany();
        abort_unless($catalogue, 404, 'The product catalogue is not currently published.');

        return $catalogue;
    }

    private function downloadName(ProductCatalogue $catalogue): string
    {
        $name = Str::slug($catalogue->title ?: 'emerald-rozalia-product-catalogue');
        if ($catalogue->version) {
            $name .= '-'.Str::slug($catalogue->version);
        }

        return ($name ?: 'emerald-rozalia-product-catalogue').'.pdf';
    }
}
