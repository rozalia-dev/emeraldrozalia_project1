<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCatalogue;
use App\Services\PublicMediaResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCatalogueController extends Controller
{
    public function show(PublicMediaResolver $mediaResolver)
    {
        [$catalogue, $products, $categories] = $this->generatedCatalogueData();

        return view('site.product-catalogue', [
            'catalogue' => $catalogue,
            'products' => $products,
            'categories' => $categories,
            'productMedia' => $this->productMedia($products, $mediaResolver),
            'uploadedPdfAvailable' => $this->uploadedPdfAvailable($catalogue),
            'generatedAt' => now(),
        ]);
    }

    public function printable(PublicMediaResolver $mediaResolver)
    {
        [$catalogue, $products, $categories] = $this->generatedCatalogueData();

        return view('site.product-catalogue-print', [
            'catalogue' => $catalogue,
            'products' => $products,
            'categories' => $categories,
            'productMedia' => $this->productMedia($products, $mediaResolver),
            'generatedAt' => now(),
        ]);
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

    private function generatedCatalogueData(): array
    {
        $companyId = ProductCatalogue::currentCompanyId();
        abort_unless($companyId, 404, 'No active company is available for the product catalogue.');

        $catalogue = ProductCatalogue::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        if (! $catalogue) {
            $catalogue = new ProductCatalogue([
                'company_id' => $companyId,
                'title' => 'Emerald Rozalia Product Catalogue',
                'description' => 'Browse the current Emerald Rozalia hats and caps range, generated directly from published products.',
                'is_published' => false,
                'download_count' => 0,
            ]);
        }

        $products = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->published()
            ->with(['category', 'media'])
            ->get()
            ->sortBy(function (Product $product): string {
                $sort = (int) ($product->category?->sort_order ?? 999999);
                $category = strtolower((string) ($product->category?->name ?? 'Other Products'));

                return sprintf('%08d|%s|%s', $sort, $category, strtolower((string) $product->name));
            })
            ->values();

        $categories = $products->groupBy(fn (Product $product): string => $product->category?->name ?: 'Other Products');

        return [$catalogue, $products, $categories];
    }

    private function productMedia(Collection $products, PublicMediaResolver $mediaResolver): array
    {
        return $products->mapWithKeys(fn (Product $product): array => [
            $product->id => $mediaResolver->forProduct($product),
        ])->all();
    }

    private function uploadedPdfAvailable(ProductCatalogue $catalogue): bool
    {
        return $catalogue->exists
            && $catalogue->is_published
            && filled($catalogue->pdf_path)
            && Storage::disk('local')->exists($catalogue->pdf_path);
    }

    private function publishedCatalogue(): ProductCatalogue
    {
        $catalogue = ProductCatalogue::publishedForCurrentCompany();
        abort_unless($catalogue, 404, 'The uploaded product catalogue PDF is not currently published.');

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
