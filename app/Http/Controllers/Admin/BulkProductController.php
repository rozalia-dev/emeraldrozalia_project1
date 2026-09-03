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
            ['file' => ['bail', 'required', 'file', 'mimes:csv,xlsx,xls', 'max:25600']],
            ['file.uploaded' => 'The file could not be uploaded. Keep it under 25 MB and use CSV, XLS, or XLSX.']
        );

        $path = $data['file']->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is no longer available. Please choose it again and retry.',
            ]);
        }

        try {
            $result = $importer->import($path, $data['file']->getClientOriginalExtension());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'file' => 'The file could not be processed. Check that it is a readable CSV, XLS, or XLSX file and try again.',
                ]);
        }

        return back()
            ->with('result', $result)
            ->with('bulk_file', $data['file']->getClientOriginalName())
            ->with('bulk_upload_id', (string) Str::uuid());
    }
}
