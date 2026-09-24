<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class BulkProductImporter
{
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function import(
        string $path,
        ?string $extension = null,
        ?string $imageZipPath = null,
        array $options = []
    ): array {
        $rows = $this->readRows($path, $extension);
        $imported = 0;
        $imagesImported = 0;
        $productsWithImages = 0;
        $errors = [];
        $warnings = [];

        $archive = null;
        $archiveIndex = null;

        if ($imageZipPath !== null) {
            [$archive, $archiveIndex] = $this->openImageArchive($imageZipPath);
        }

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = (int) ($row['__row_number'] ?? $index + 2);

                if (! empty($row['__error'])) {
                    $errors[] = ['row' => $rowNumber, 'message' => $row['__error']];
                    continue;
                }

                $storedPaths = [];

                try {
                    $result = DB::transaction(function () use (
                        $row,
                        $rowNumber,
                        $archive,
                        $archiveIndex,
                        $options,
                        &$storedPaths
                    ): array {
                        $name = trim((string) $this->value($row, ['Product Name', 'name'], ''));
                        $sku = trim((string) $this->value($row, ['SKU', 'sku'], ''));

                        if ($name === '' || $sku === '') {
                            throw new InvalidArgumentException('Product Name and SKU are required.');
                        }

                        $categoryName = trim((string) $this->value($row, ['Category', 'category'], 'Uncategorised'));
                        $category = $this->resolveCategory($categoryName !== '' ? $categoryName : 'Uncategorised');

                        $defaultStatus = strtolower(trim((string) ($options['default_status'] ?? 'active')));
                        $rawStatus = strtolower(trim((string) $this->value($row, ['Status', 'status'], $defaultStatus)));
                        $status = in_array($rawStatus, ['active', 'published', 'draft', 'inactive'], true)
                            ? $rawStatus
                            : 'active';

                        $product = Product::updateOrCreate(
                            ['sku' => $sku],
                            [
                                'category_id' => $category->id,
                                'name' => $name,
                                'slug' => Str::slug($name.'-'.$sku),
                                'price' => $this->number($this->value($row, ['Price', 'Price (EUR)', 'price'], 0)),
                                'stock' => $this->integer($this->value($row, ['Stock', 'stock'], 0)),
                                'description' => $this->nullableString($this->value($row, ['Description', 'description'])),
                                'material' => $this->nullableString($this->value($row, ['Material', 'material'])),
                                'status' => $status,
                                'is_active' => in_array($status, ['active', 'published'], true),
                            ]
                        );

                        if (! $archive || ! is_array($archiveIndex)) {
                            return ['images' => 0, 'product_with_images' => false, 'warnings' => []];
                        }

                        $references = $this->imageReferences($row, $sku, $archiveIndex);
                        $rowWarnings = [];
                        $attached = 0;
                        $firstImagePath = null;

                        foreach (array_slice($references, 0, 6) as $position => $reference) {
                            try {
                                $entryIndex = $this->resolveArchiveEntry($reference, $sku, $archiveIndex);
                                [$contents, $mime, $width, $height, $entryName] = $this->readArchiveImage($archive, $entryIndex);

                                $extensionForMime = self::ALLOWED_IMAGE_MIMES[$mime];
                                $storagePath = 'product-media/'.$product->id.'/bulk/'
                                    .str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT)
                                    .'-'.substr(sha1($contents), 0, 16).'.'.$extensionForMime;

                                $alreadyExists = Storage::disk('public')->exists($storagePath);
                                if (! Storage::disk('public')->put($storagePath, $contents)) {
                                    throw new InvalidArgumentException('The image could not be written to product media storage.');
                                }
                                if (! $alreadyExists) {
                                    $storedPaths[] = $storagePath;
                                }

                                $approveImages = (bool) ($options['approve_images'] ?? false);
                                $media = ProductMedia::query()->firstOrNew([
                                    'product_id' => $product->id,
                                    'disk' => 'public',
                                    'path' => $storagePath,
                                ]);

                                if (! $media->exists) {
                                    $media->uuid = (string) Str::uuid();
                                }

                                $media->fill([
                                    'type' => 'image',
                                    'alt_text' => $name.' view '.($position + 1),
                                    'sort_order' => $position + 1,
                                    'active' => true,
                                    'approval_status' => $approveImages ? 'approved' : 'pending',
                                    'approved_at' => $approveImages ? now() : null,
                                    'approved_by' => $approveImages ? auth()->id() : null,
                                    'mime_type' => $mime,
                                    'width' => $width,
                                    'height' => $height,
                                    'bytes' => strlen($contents),
                                    'metadata' => [
                                        'source' => 'bulk-product-image-zip',
                                        'archive_path' => $entryName,
                                        'original_name' => basename($entryName),
                                        'row' => $rowNumber,
                                        'view' => $position + 1,
                                    ],
                                ]);
                                $media->save();

                                $firstImagePath ??= $storagePath;
                                $attached++;
                            } catch (\Throwable $imageException) {
                                $rowWarnings[] = 'Image "'.$reference.'": '.$imageException->getMessage();
                            }
                        }

                        if ($firstImagePath !== null && $product->image !== $firstImagePath) {
                            $product->update(['image' => $firstImagePath]);
                        }

                        return [
                            'images' => $attached,
                            'product_with_images' => $attached > 0,
                            'warnings' => $rowWarnings,
                        ];
                    });

                    $imported++;
                    $imagesImported += (int) $result['images'];
                    if ($result['product_with_images']) {
                        $productsWithImages++;
                    }
                    foreach ($result['warnings'] as $warning) {
                        $warnings[] = ['row' => $rowNumber, 'message' => $warning];
                    }
                } catch (\Throwable $exception) {
                    foreach ($storedPaths as $storedPath) {
                        Storage::disk('public')->delete($storedPath);
                    }
                    $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
                }
            }
        } finally {
            if ($archive instanceof ZipArchive) {
                $archive->close();
            }
        }

        return [
            'imported' => $imported,
            'images_imported' => $imagesImported,
            'products_with_images' => $productsWithImages,
            'errors' => $errors,
            'warnings' => $warnings,
            'total' => count($rows),
        ];
    }

    public function preview(string $path, ?string $extension = null, int $limit = 5): array
    {
        $rows = $this->readRows($path, $extension);
        $preview = [];
        $sample = [];
        $valid = 0;
        $errors = 0;

        foreach ($rows as $row) {
            if (! empty($row['__error'])) {
                $errors++;
                continue;
            }

            $name = trim((string) $this->value($row, ['Product Name', 'name'], ''));
            $sku = trim((string) $this->value($row, ['SKU', 'sku'], ''));

            if ($name === '' || $sku === '') {
                $errors++;
                continue;
            }

            $valid++;

            if ($sample === []) {
                foreach ($row as $header => $value) {
                    if (str_starts_with((string) $header, '__')) {
                        continue;
                    }
                    $sample[$this->key((string) $header)] = is_scalar($value) || $value === null
                        ? (string) ($value ?? '')
                        : '';
                }
            }

            if (count($preview) >= max(1, min(20, $limit))) {
                continue;
            }

            $imageCount = 0;
            foreach (range(1, 6) as $position) {
                if (trim((string) $this->value($row, ['Image '.$position, 'Image'.$position, 'Image_'.$position], '')) !== '') {
                    $imageCount++;
                }
            }

            if ($imageCount === 0) {
                $combined = trim((string) $this->value($row, ['Images', 'images'], ''));
                if ($combined !== '') {
                    $imageCount = count(array_filter(array_map('trim', preg_split('/[|;,]+/', $combined) ?: [])));
                }
            }

            $preview[] = [
                'name' => $name,
                'sku' => $sku,
                'category' => trim((string) $this->value($row, ['Category', 'category'], '')),
                'price' => $this->number($this->value($row, ['Price', 'Price (EUR)', 'price'], 0)),
                'stock' => $this->integer($this->value($row, ['Stock', 'stock'], 0)),
                'status' => trim((string) $this->value($row, ['Status', 'status'], '')),
                'images' => min(6, $imageCount),
            ];
        }

        return [
            'total' => count($rows),
            'valid' => $valid,
            'errors' => $errors,
            'rows' => $preview,
            'sample' => $sample,
        ];
    }

    private function resolveCategory(string $name): Category
    {
        $name = trim($name);
        $slug = Str::slug($name);

        $category = Category::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if (! $category && $slug === 'gift-for-her') {
            $category = Category::query()->where('slug', 'gift')->first();
        }

        if (! $category) {
            $category = Category::query()->where('slug', $slug)->first();
        }

        if ($category) {
            return $category;
        }

        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => ((int) Category::query()->max('sort_order')) + 1,
        ]);
    }

    private function openImageArchive(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('The product images ZIP cannot be read.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new InvalidArgumentException('ZIP support is not available on this server.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The product images ZIP is invalid or damaged.');
        }

        if ($zip->numFiles > 10000) {
            $zip->close();
            throw new InvalidArgumentException('The product images ZIP contains too many files.');
        }

        $byPath = [];
        $byBasename = [];
        $bySku = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $normalized = $this->normalizeArchivePath($name);
            if ($normalized === null || ! $this->isSupportedImageName($normalized)) {
                continue;
            }

            $lowerPath = Str::lower($normalized);
            $basename = Str::lower(basename($normalized));
            $byPath[$lowerPath] = $i;
            $byBasename[$basename][] = $i;

            foreach (explode('/', $normalized) as $segment) {
                $key = Str::lower(trim($segment));
                if ($key !== '' && preg_match('/^[a-z0-9][a-z0-9._-]{2,100}$/i', $key)) {
                    $bySku[$key][] = $i;
                }
            }
        }

        return [$zip, [
            'by_path' => $byPath,
            'by_basename' => $byBasename,
            'by_sku' => $bySku,
        ]];
    }

    private function imageReferences(array $row, string $sku, array $archiveIndex): array
    {
        $references = [];

        foreach (range(1, 6) as $position) {
            $value = trim((string) $this->value($row, [
                'Image '.$position,
                'Image'.$position,
                'Image_'.$position,
            ], ''));

            if ($value !== '') {
                $references[] = $value;
            }
        }

        $combined = trim((string) $this->value($row, ['Images', 'images'], ''));
        if ($combined !== '') {
            foreach (preg_split('/[|;,]+/', $combined) ?: [] as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $references[] = $value;
                }
            }
        }

        $references = array_values(array_unique($references));

        if ($references !== []) {
            return $references;
        }

        $indices = $archiveIndex['by_sku'][Str::lower($sku)] ?? [];
        $entries = [];
        foreach (array_values(array_unique($indices)) as $index) {
            $entries[] = ['__archive_index__' => $index];
        }

        return array_slice($entries, 0, 6);
    }

    private function resolveArchiveEntry(mixed $reference, string $sku, array $archiveIndex): int
    {
        if (is_array($reference) && isset($reference['__archive_index__'])) {
            return (int) $reference['__archive_index__'];
        }

        $reference = trim((string) $reference);
        if ($reference === '') {
            throw new InvalidArgumentException('Image filename is empty.');
        }

        $normalized = $this->normalizeArchivePath($reference);
        if ($normalized === null) {
            throw new InvalidArgumentException('Image path is not allowed.');
        }

        $lowerPath = Str::lower($normalized);
        if (isset($archiveIndex['by_path'][$lowerPath])) {
            return (int) $archiveIndex['by_path'][$lowerPath];
        }

        $basename = Str::lower(basename($normalized));
        $candidates = array_values(array_unique($archiveIndex['by_basename'][$basename] ?? []));
        if ($candidates === []) {
            throw new InvalidArgumentException('Image was not found in the ZIP.');
        }

        if (count($candidates) === 1) {
            return (int) $candidates[0];
        }

        $skuCandidates = array_values(array_intersect(
            $candidates,
            array_values(array_unique($archiveIndex['by_sku'][Str::lower($sku)] ?? []))
        ));

        if (count($skuCandidates) === 1) {
            return (int) $skuCandidates[0];
        }

        throw new InvalidArgumentException('Image filename is duplicated in the ZIP; use a folder path or SKU folder to disambiguate it.');
    }

    private function readArchiveImage(ZipArchive $archive, int $index): array
    {
        $stat = $archive->statIndex($index);
        if (! is_array($stat)) {
            throw new InvalidArgumentException('Image ZIP entry could not be read.');
        }

        $entryName = (string) ($stat['name'] ?? '');
        $size = (int) ($stat['size'] ?? 0);
        if ($size < 1 || $size > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('Each product image must be between 1 byte and 25 MB.');
        }

        $contents = $archive->getFromIndex($index);
        if (! is_string($contents) || $contents === '') {
            throw new InvalidArgumentException('Image ZIP entry is empty or unreadable.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($contents);
        if (! isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
            throw new InvalidArgumentException('Only JPG, PNG, WEBP, and AVIF product images are allowed.');
        }

        $dimensions = @getimagesizefromstring($contents);
        $width = is_array($dimensions) ? ((int) ($dimensions[0] ?? 0) ?: null) : null;
        $height = is_array($dimensions) ? ((int) ($dimensions[1] ?? 0) ?: null) : null;

        return [$contents, $mime, $width, $height, $entryName];
    }

    private function normalizeArchivePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        if ($path === ''
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path)
            || in_array('..', explode('/', $path), true)) {
            return null;
        }

        return preg_replace('#/+#', '/', $path) ?: null;
    }

    private function isSupportedImageName(string $path): bool
    {
        return in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'avif'], true);
    }

    private function readRows(string $path, ?string $extension = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('The uploaded file cannot be read.');
        }

        $extension = strtolower(trim((string) ($extension ?: pathinfo($path, PATHINFO_EXTENSION))));

        return match ($extension) {
            'csv' => $this->readCsv($path),
            'xls', 'xlsx' => $this->readSpreadsheet($path),
            default => throw new InvalidArgumentException('Only CSV, XLS, and XLSX files are supported.'),
        };
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('The CSV file cannot be opened.');
        }

        try {
            $rawHeaders = fgetcsv($handle);

            if ($rawHeaders === false) {
                throw new InvalidArgumentException('The CSV file is empty.');
            }

            $headers = $this->headers($rawHeaders);
            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->blank($values)) {
                    continue;
                }

                $rows[] = $this->mapRow($headers, $values, $line);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function readSpreadsheet(string $path): array
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('The spreadsheet could not be read. Please export it again as XLS or XLSX.', 0, $exception);
        }

        if ($sheet === []) {
            throw new InvalidArgumentException('The spreadsheet is empty.');
        }

        $rawHeaders = array_shift($sheet);
        $headers = $this->headers(array_values($rawHeaders ?: []));
        $rows = [];

        foreach (array_values($sheet) as $index => $values) {
            $values = array_values($values);

            if ($this->blank($values)) {
                continue;
            }

            $rows[] = $this->mapRow($headers, $values, $index + 2);
        }

        return $rows;
    }

    private function headers(array $headers): array
    {
        $headers = array_map(function ($header): string {
            $header = trim((string) $header);

            return preg_replace('/^\xEF\xBB\xBF/u', '', $header) ?? $header;
        }, array_values($headers));

        if ($headers === [] || in_array('', $headers, true)) {
            throw new InvalidArgumentException('The file must contain a header row.');
        }

        $keys = array_map(fn (string $header): string => $this->key($header), $headers);

        if (count(array_unique($keys)) !== count($keys)) {
            throw new InvalidArgumentException('The file contains duplicate column headers.');
        }

        return $headers;
    }

    private function mapRow(array $headers, array $values, int $rowNumber): array
    {
        if (count($values) !== count($headers)) {
            return [
                '__row_number' => $rowNumber,
                '__error' => sprintf(
                    'Expected %d columns but found %d.',
                    count($headers),
                    count($values)
                ),
            ];
        }

        return array_combine($headers, $values) + ['__row_number' => $rowNumber];
    }

    private function blank(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function value(array $row, array $aliases, mixed $default = null): mixed
    {
        $keys = array_map(fn (string $alias): string => $this->key($alias), $aliases);

        foreach ($row as $header => $value) {
            if (str_starts_with((string) $header, '__')) {
                continue;
            }

            if (in_array($this->key((string) $header), $keys, true)) {
                return $value;
            }
        }

        return $default;
    }

    private function key(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/u', '', trim($value)) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function number(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $normalized = preg_replace('/[^0-9,.-]+/u', '', $value) ?? '';

        if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return 0;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($lastComma !== false) {
            if (preg_match('/,\d{1,2}$/', $normalized)) {
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        }

        $number = (float) $normalized;

        return $negative ? -abs($number) : $number;
    }

    private function integer(mixed $value): int
    {
        $value = trim((string) $value);

        return $value === '' ? 0 : (int) $value;
    }
}
