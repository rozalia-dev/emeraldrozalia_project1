<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkProductImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BulkProductController extends Controller
{
    public function index()
    {
        return view('admin.bulk-upload');
    }

    public function store(Request $request, BulkProductImporter $importer): RedirectResponse
    {
        $data = $request->validate(
            [
                'file' => ['bail', 'required', 'file', 'mimes:csv,xlsx,xls', 'max:25600'],
                'images_zip' => ['nullable', 'file', 'mimes:zip', 'max:1048576'],
                'approve_images' => ['nullable', 'boolean'],
                'default_status' => ['nullable', 'string', 'max:30'],
            ],
            [
                'file.uploaded' => 'The product file could not be uploaded. Keep it under 25 MB and use CSV, XLS, or XLSX.',
                'images_zip.uploaded' => 'The image ZIP could not be uploaded. Keep it under 1 GB and try again.',
                'images_zip.mimes' => 'Product images must be supplied as a ZIP file.',
            ]
        );

        $path = $data['file']->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded product file is no longer available. Please choose it again and retry.',
            ]);
        }

        $imageZipPath = null;
        if ($request->hasFile('images_zip')) {
            $imageZipPath = $request->file('images_zip')->getRealPath();

            if ($imageZipPath === false) {
                throw ValidationException::withMessages([
                    'images_zip' => 'The uploaded image ZIP is no longer available. Please choose it again and retry.',
                ]);
            }
        }

        try {
            $result = $importer->import(
                $path,
                $data['file']->getClientOriginalExtension(),
                $imageZipPath,
                [
                    'approve_images' => $request->boolean('approve_images'),
                    'default_status' => strtolower(trim((string) ($data['default_status'] ?? 'active'))),
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    $imageZipPath !== null ? 'images_zip' : 'file' =>
                        $imageZipPath !== null
                            ? 'The import could not be processed. Check the spreadsheet image names and ZIP contents, then try again.'
                            : 'The product file could not be processed. Check that it is a readable CSV, XLS, or XLSX file and try again.',
                ]);
        }

        return back()
            ->with('result', $result)
            ->with('bulk_file', $data['file']->getClientOriginalName())
            ->with('bulk_images_file', $request->file('images_zip')?->getClientOriginalName())
            ->with('bulk_upload_id', (string) Str::uuid());
    }
}
