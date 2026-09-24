<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkProductImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class BulkProductController extends Controller
{
    private const IMAGE_ZIP_MAX_BYTES = 1073741824;
    private const IMAGE_CHUNK_BYTES = 524288;
    private const IMAGE_CHUNK_MAX_KB = 600;
    private const IMAGE_UPLOAD_SESSION_KEY = 'bulk_product_image_uploads';
    private const IMAGE_UPLOAD_TTL_SECONDS = 7200;

    public function index()
    {
        return view('admin.bulk-upload');
    }

    public function downloadCsvTemplate(): StreamedResponse
    {
        $headers = $this->productTemplateHeaders();
        $sample = $this->productTemplateExampleRow();

        return response()->streamDownload(function () use ($headers, $sample): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            fputcsv($output, $sample);
            fclose($output);
        }, 'emerald-rozalia-bulk-product-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadXlsxTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $products = $spreadsheet->getActiveSheet();
        $products->setTitle('Products');
        $products->fromArray([
            $this->productTemplateHeaders(),
            $this->productTemplateExampleRow(),
        ], null, 'A1');

        $products->freezePane('A2');
        $products->getStyle('A1:N1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $products->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B4F3A');
        $products->getStyle('D2:D500')->getNumberFormat()->setFormatCode('€0.00');
        $products->getColumnDimension('A')->setWidth(38);
        $products->getColumnDimension('B')->setWidth(18);
        $products->getColumnDimension('C')->setWidth(20);
        $products->getColumnDimension('D')->setWidth(13);
        $products->getColumnDimension('E')->setWidth(12);
        $products->getColumnDimension('F')->setWidth(44);
        $products->getColumnDimension('G')->setWidth(24);
        $products->getColumnDimension('H')->setWidth(14);
        foreach (range('I', 'N') as $column) {
            $products->getColumnDimension($column)->setWidth(18);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Emerald Rozalia Bulk Product + Image Template', ''],
            ['Required fields', 'Product Name and SKU are required. Category, Price and Stock should also be supplied for normal catalogue imports.'],
            ['Image option A', 'Put up to six image filenames in Image 1 through Image 6. Use the same filenames inside the matching SKU folder in the image ZIP.'],
            ['Image option B', 'Leave Image 1 through Image 6 blank and place images inside a ZIP folder named exactly as the product SKU.'],
            ['Recommended names', 'view-01.jpg, view-02.jpg, view-03.jpg, view-04.jpg, view-05.jpg, view-06.jpg'],
            ['View order', '01 Front, 02 Back, 03 Left, 04 Right, 05 Inside, 06 Detail/Packaging.'],
            ['Supported images', 'JPG, PNG, WEBP, AVIF. Maximum six images are attached per product.'],
            ['Status', 'Use published, active, draft or inactive.'],
            ['Update existing products', 'Keep Update existing products enabled to match existing products by SKU without creating duplicates.'],
        ], null, 'A1');
        $instructions->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $instructions->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B4F3A');
        $instructions->getColumnDimension('A')->setWidth(28);
        $instructions->getColumnDimension('B')->setWidth(100);
        $instructions->getStyle('A1:B20')->getAlignment()->setWrapText(true);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'emerald-rozalia-bulk-product-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadImageZipTemplate(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'emerald-bulk-images-');
        if ($path === false) {
            abort(500, 'Could not create the image ZIP template.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            abort(500, 'Could not create the image ZIP template.');
        }

        $readme = <<<'TXT'
EMERALD ROZALIA — BULK PRODUCT IMAGE ZIP TEMPLATE

1. Rename each sample folder to the EXACT product SKU from your CSV/XLSX.
   Example: ER-HER-001, ER-HER-002, ER-GFH-001.

2. Put up to six product images inside each SKU folder.

3. Recommended filenames:
   view-01.jpg  = Front
   view-02.jpg  = Back
   view-03.jpg  = Left side
   view-04.jpg  = Right side
   view-05.jpg  = Inside
   view-06.jpg  = Detail / packaging

4. Supported formats: JPG, PNG, WEBP, AVIF.

5. You may reuse view-01.jpg ... view-06.jpg in every SKU folder.
   The importer uses the SKU folder to match duplicate filenames safely.

6. If your spreadsheet contains Image 1 ... Image 6 columns, those filenames
   should match the files inside that product's SKU folder.

7. Re-ZIP the SKU folders and upload the ZIP beside the CSV/XLSX on:
   Admin > Bulk Product Upload.

IMPORTANT:
- Never rename the SKU folder differently from the spreadsheet SKU.
- Keep "Update existing products (match by SKU)" ON when adding images to
  products that already exist.
- Keep "Approve imported images" ON when the images should be public-ready.
TXT;

        $mapping = implode("\n", [
            'SKU,Image 1,Image 2,Image 3,Image 4,Image 5,Image 6',
            'ER-SAMPLE-001,view-01.jpg,view-02.jpg,view-03.jpg,view-04.jpg,view-05.jpg,view-06.jpg',
            'ER-SAMPLE-002,view-01.jpg,view-02.jpg,view-03.jpg,view-04.jpg,view-05.jpg,view-06.jpg',
            '',
        ]);

        $zip->addFromString('README.txt', $readme);
        $zip->addFromString('IMAGE-MAPPING-EXAMPLE.csv', $mapping);

        foreach (['ER-SAMPLE-001', 'ER-SAMPLE-002'] as $sku) {
            $zip->addEmptyDir($sku);
            $zip->addFromString(
                $sku.'/PUT-YOUR-IMAGES-HERE.txt',
                "Replace this text file with up to six product images named view-01.jpg through view-06.jpg.\n"
            );
        }

        $zip->close();

        return response()
            ->download($path, 'emerald-rozalia-bulk-image-template.zip', [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    public function preview(Request $request, BulkProductImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'file' => ['bail', 'required', 'file', 'mimes:csv,xlsx,xls', 'max:25600'],
        ]);

        $path = $data['file']->getRealPath();
        if ($path === false) {
            return response()->json(['message' => 'The uploaded product file is no longer available.'], 422);
        }

        try {
            return response()->json(
                $importer->preview($path, $data['file']->getClientOriginalExtension(), 5)
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The product file could not be previewed. Check that it is a readable CSV, XLS, or XLSX file.',
            ], 422);
        }
    }

    public function imageUploadInit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.self::IMAGE_ZIP_MAX_BYTES],
        ]);

        $filename = basename(str_replace('\\', '/', trim($data['filename'])));
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') {
            return response()->json(['message' => 'Product images must be supplied as a ZIP file.'], 422);
        }

        $this->cleanupStaleImageUploads($request);

        $token = (string) Str::uuid();
        $directory = $this->imageUploadDirectory($request, $token);
        File::ensureDirectoryExists($directory, 0700, true);

        $size = (int) $data['size'];
        $totalChunks = (int) ceil($size / self::IMAGE_CHUNK_BYTES);

        $uploads = $request->session()->get(self::IMAGE_UPLOAD_SESSION_KEY, []);
        $uploads[$token] = [
            'filename' => $filename,
            'directory' => $directory,
            'path' => $directory.'/archive.zip',
            'size' => $size,
            'total_chunks' => $totalChunks,
            'received' => [],
            'completed' => false,
            'updated_at' => time(),
        ];
        $request->session()->put(self::IMAGE_UPLOAD_SESSION_KEY, $uploads);

        return response()->json([
            'token' => $token,
            'chunk_size' => self::IMAGE_CHUNK_BYTES,
            'total_chunks' => $totalChunks,
        ]);
    }

    public function imageUploadChunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.self::IMAGE_CHUNK_MAX_KB],
        ]);

        $uploads = $request->session()->get(self::IMAGE_UPLOAD_SESSION_KEY, []);
        $token = (string) $data['token'];
        $upload = $uploads[$token] ?? null;

        if (! is_array($upload) || ($upload['completed'] ?? false)) {
            return response()->json(['message' => 'This product-image upload is no longer active. Please select the ZIP again.'], 422);
        }

        $index = (int) $data['index'];
        $totalChunks = (int) ($upload['total_chunks'] ?? 0);

        if ($index >= $totalChunks) {
            return response()->json(['message' => 'The ZIP upload chunk number is invalid.'], 422);
        }

        $directory = (string) $upload['directory'];
        if (! str_starts_with($directory, $this->imageUploadBaseDirectory($request).DIRECTORY_SEPARATOR)) {
            return response()->json(['message' => 'The ZIP upload session is invalid.'], 422);
        }

        File::ensureDirectoryExists($directory, 0700, true);
        $partPath = $directory.'/chunk-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part';
        $sourcePath = $data['chunk']->getRealPath();

        if ($sourcePath === false) {
            return response()->json(['message' => 'The uploaded ZIP chunk could not be read.'], 422);
        }

        $bytes = (int) $data['chunk']->getSize();
        if ($bytes < 1 || $bytes > self::IMAGE_CHUNK_BYTES) {
            return response()->json(['message' => 'The uploaded ZIP chunk has an invalid size.'], 422);
        }

        $input = fopen($sourcePath, 'rb');
        $output = fopen($partPath, 'wb');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }

            return response()->json(['message' => 'The ZIP chunk could not be stored.'], 500);
        }

        $copied = stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);

        if ($copied !== $bytes) {
            @unlink($partPath);

            return response()->json(['message' => 'The ZIP chunk was not stored completely. Please retry.'], 500);
        }

        $received = is_array($upload['received'] ?? null) ? $upload['received'] : [];
        $received[(string) $index] = $bytes;
        $upload['received'] = $received;
        $upload['updated_at'] = time();
        $uploads[$token] = $upload;
        $request->session()->put(self::IMAGE_UPLOAD_SESSION_KEY, $uploads);

        $receivedBytes = array_sum(array_map('intval', $received));
        $progress = min(100, (int) floor(($receivedBytes / max(1, (int) $upload['size'])) * 100));

        return response()->json([
            'received_chunks' => count($received),
            'total_chunks' => $totalChunks,
            'received_bytes' => $receivedBytes,
            'progress' => $progress,
        ]);
    }

    public function imageUploadComplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
        ]);

        $uploads = $request->session()->get(self::IMAGE_UPLOAD_SESSION_KEY, []);
        $token = (string) $data['token'];
        $upload = $uploads[$token] ?? null;

        if (! is_array($upload)) {
            return response()->json(['message' => 'This product-image upload expired. Please select the ZIP again.'], 422);
        }

        if ($upload['completed'] ?? false) {
            return response()->json([
                'token' => $token,
                'filename' => $upload['filename'],
                'size' => (int) $upload['size'],
            ]);
        }

        $totalChunks = (int) $upload['total_chunks'];
        $received = is_array($upload['received'] ?? null) ? $upload['received'] : [];

        if (count($received) !== $totalChunks) {
            return response()->json(['message' => 'The product-image ZIP is not fully uploaded yet.'], 422);
        }

        $directory = (string) $upload['directory'];
        $finalPath = (string) $upload['path'];
        $output = fopen($finalPath, 'wb');

        if ($output === false) {
            return response()->json(['message' => 'The product-image ZIP could not be assembled.'], 500);
        }

        try {
            foreach (range(0, $totalChunks - 1) as $index) {
                $partPath = $directory.'/chunk-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part';

                if (! is_file($partPath)) {
                    throw new \RuntimeException('A ZIP upload chunk is missing. Please select the ZIP again.');
                }

                $input = fopen($partPath, 'rb');
                if ($input === false) {
                    throw new \RuntimeException('A ZIP upload chunk could not be read.');
                }

                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } catch (Throwable $exception) {
            fclose($output);
            @unlink($finalPath);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        fclose($output);

        if ((int) filesize($finalPath) !== (int) $upload['size']) {
            @unlink($finalPath);

            return response()->json(['message' => 'The assembled ZIP size does not match the selected file. Please retry.'], 422);
        }

        $zip = new ZipArchive();
        $opened = $zip->open($finalPath);
        if ($opened !== true) {
            @unlink($finalPath);

            return response()->json(['message' => 'The selected product-image ZIP is invalid or damaged.'], 422);
        }

        if ($zip->numFiles > 10000) {
            $zip->close();
            @unlink($finalPath);

            return response()->json(['message' => 'The product-image ZIP contains too many files.'], 422);
        }

        $zip->close();

        foreach (range(0, $totalChunks - 1) as $index) {
            @unlink($directory.'/chunk-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part');
        }

        $upload['completed'] = true;
        $upload['updated_at'] = time();
        unset($upload['received']);
        $uploads[$token] = $upload;
        $request->session()->put(self::IMAGE_UPLOAD_SESSION_KEY, $uploads);

        return response()->json([
            'token' => $token,
            'filename' => $upload['filename'],
            'size' => (int) $upload['size'],
        ]);
    }

    public function store(Request $request, BulkProductImporter $importer): RedirectResponse
    {
        $data = $request->validate(
            [
                'file' => ['bail', 'required', 'file', 'mimes:csv,xlsx,xls', 'max:25600'],
                'images_zip' => ['nullable', 'file', 'mimes:zip', 'max:1048576'],
                'images_zip_token' => ['nullable', 'uuid'],
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
        $bulkImagesFile = null;
        $chunkedToken = trim((string) ($data['images_zip_token'] ?? ''));

        if ($chunkedToken !== '') {
            $chunkedUpload = $this->completedImageUpload($request, $chunkedToken);
            $imageZipPath = $chunkedUpload['path'];
            $bulkImagesFile = $chunkedUpload['filename'];
        } elseif ($request->hasFile('images_zip')) {
            $imageZipPath = $request->file('images_zip')->getRealPath();
            $bulkImagesFile = $request->file('images_zip')->getClientOriginalName();

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

        if ($chunkedToken !== '') {
            $this->deleteImageUpload($request, $chunkedToken);
        }

        return back()
            ->with('result', $result)
            ->with('bulk_file', $data['file']->getClientOriginalName())
            ->with('bulk_images_file', $bulkImagesFile)
            ->with('bulk_upload_id', (string) Str::uuid());
    }

    private function productTemplateHeaders(): array
    {
        return [
            'Product Name',
            'SKU',
            'Category',
            'Price',
            'Stock',
            'Description',
            'Material',
            'Status',
            'Image 1',
            'Image 2',
            'Image 3',
            'Image 4',
            'Image 5',
            'Image 6',
        ];
    }

    private function productTemplateExampleRow(): array
    {
        return [
            'Sample Heritage Bucket Hat',
            'ER-SAMPLE-001',
            'Heritage',
            79,
            100,
            'Premium Irish-made hat.',
            '100% Irish Tweed',
            'published',
            'view-01.jpg',
            'view-02.jpg',
            'view-03.jpg',
            'view-04.jpg',
            'view-05.jpg',
            'view-06.jpg',
        ];
    }

    private function completedImageUpload(Request $request, string $token): array
    {
        $uploads = $request->session()->get(self::IMAGE_UPLOAD_SESSION_KEY, []);
        $upload = $uploads[$token] ?? null;

        if (! is_array($upload)
            || ! ($upload['completed'] ?? false)
            || ! is_file((string) ($upload['path'] ?? ''))) {
            throw ValidationException::withMessages([
                'images_zip' => 'The product-image ZIP upload is incomplete or expired. Please select the ZIP again.',
            ]);
        }

        return $upload;
    }

    private function imageUploadBaseDirectory(Request $request): string
    {
        return storage_path('app/private/bulk-product-upload/'.(int) $request->user()->id);
    }

    private function imageUploadDirectory(Request $request, string $token): string
    {
        return $this->imageUploadBaseDirectory($request).DIRECTORY_SEPARATOR.$token;
    }

    private function cleanupStaleImageUploads(Request $request): void
    {
        $uploads = $request->session()->get(self::IMAGE_UPLOAD_SESSION_KEY, []);
        $cutoff = time() - self::IMAGE_UPLOAD_TTL_SECONDS;

        foreach ($uploads as $token => $upload) {
            if ((int) ($upload['updated_at'] ?? 0) < $cutoff) {
                if (is_dir((string) ($upload['directory'] ?? ''))) {
                    File::deleteDirectory((string) $upload['directory']);
                }
                unset($uploads[$token]);
            }
        }

        $request->session()->put(self::IMAGE_UPLOAD_SESSION_KEY, $uploads);

        $base = $this->imageUploadBaseDirectory($request);
        if (! is_dir($base)) {
            return;
        }

        foreach (File::directories($base) as $directory) {
            if ((int) File::lastModified($directory) < $cutoff) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function deleteImageUpload(Request $request, string $token): void
    {
        $uploads = $request->session()->get(self::IMAGE_UPLOAD_SESSION_KEY, []);
        $upload = $uploads[$token] ?? null;

        if (is_array($upload) && is_dir((string) ($upload['directory'] ?? ''))) {
            File::deleteDirectory((string) $upload['directory']);
        }

        unset($uploads[$token]);
        $request->session()->put(self::IMAGE_UPLOAD_SESSION_KEY, $uploads);
    }
}
