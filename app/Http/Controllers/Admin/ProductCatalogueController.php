<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCatalogueController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();
        $catalogue = ProductCatalogue::withoutGlobalScopes()->firstOrNew(
            ['company_id' => $companyId],
            ['title' => 'Emerald Rozalia Product Catalogue', 'is_published' => false, 'download_count' => 0]
        );

        return view('admin.product-catalogue.index', compact('catalogue'));
    }

    public function update(Request $request)
    {
        $companyId = $this->companyId();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'version' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'catalogue_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $catalogue = ProductCatalogue::withoutGlobalScopes()->firstOrNew(['company_id' => $companyId]);
        $oldPdf = $catalogue->pdf_path;
        $oldCover = $catalogue->cover_path;
        $newPdf = null;
        $newCover = null;

        try {
            if ($pdf = $request->file('catalogue_pdf')) {
                $newPdf = $pdf->storeAs('catalogues/'.$companyId, (string) Str::uuid().'.pdf', 'local');
                if (! $newPdf) {
                    throw ValidationException::withMessages(['catalogue_pdf' => 'The catalogue PDF could not be stored.']);
                }
                $catalogue->pdf_path = $newPdf;
                $catalogue->pdf_original_name = $pdf->getClientOriginalName();
                $catalogue->pdf_size = $pdf->getSize();
            }

            if ($cover = $request->file('cover_image')) {
                $extension = strtolower($cover->extension() ?: 'jpg');
                $newCover = $cover->storeAs('catalogues/'.$companyId.'/covers', (string) Str::uuid().'.'.$extension, 'local');
                if (! $newCover) {
                    throw ValidationException::withMessages(['cover_image' => 'The catalogue cover could not be stored.']);
                }
                $catalogue->cover_path = $newCover;
                $catalogue->cover_original_name = $cover->getClientOriginalName();
            }

            $publish = $request->boolean('is_published');
            if ($publish && ! $catalogue->pdf_path) {
                throw ValidationException::withMessages(['catalogue_pdf' => 'Upload a PDF before publishing the catalogue.']);
            }

            $catalogue->title = $validated['title'];
            $catalogue->version = $validated['version'] ?? null;
            $catalogue->description = $validated['description'] ?? null;
            $catalogue->is_published = $publish;
            $catalogue->published_at = $publish ? ($catalogue->published_at ?: now()) : null;
            $catalogue->updated_by = $request->user()->id;
            if (! $catalogue->exists) {
                $catalogue->created_by = $request->user()->id;
            }
            $catalogue->save();
        } catch (\Throwable $exception) {
            if ($newPdf) {
                Storage::disk('local')->delete($newPdf);
            }
            if ($newCover) {
                Storage::disk('local')->delete($newCover);
            }
            throw $exception;
        }

        if ($newPdf && $oldPdf && $oldPdf !== $newPdf) {
            Storage::disk('local')->delete($oldPdf);
        }
        if ($newCover && $oldCover && $oldCover !== $newCover) {
            Storage::disk('local')->delete($oldCover);
        }

        return redirect()->route('admin.product-catalogue.index')->with('success', 'Product catalogue settings saved.');
    }

    public function download(): StreamedResponse
    {
        $catalogue = ProductCatalogue::withoutGlobalScopes()->where('company_id', $this->companyId())->firstOrFail();
        abort_unless($catalogue->pdf_path && Storage::disk('local')->exists($catalogue->pdf_path), 404);

        return Storage::disk('local')->download(
            $catalogue->pdf_path,
            $catalogue->pdf_original_name ?: 'product-catalogue.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function companyId(): int
    {
        $companyId = (int) session('company_id');
        abort_unless($companyId > 0, 403, 'Select a company context before managing the product catalogue.');

        return $companyId;
    }
}
