<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BulkProductImporter
{
    public function import(string $path, ?string $extension = null): array
    {
        $rows = $this->readRows($path, $extension);
        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = (int) ($row['__row_number'] ?? $index + 2);

            if (! empty($row['__error'])) {
                $errors[] = ['row' => $rowNumber, 'message' => $row['__error']];
                continue;
            }

            try {
                $name = trim((string) $this->value($row, ['Product Name', 'name'], ''));
                $sku = trim((string) $this->value($row, ['SKU', 'sku'], ''));

                if ($name === '' || $sku === '') {
                    throw new InvalidArgumentException('Product Name and SKU are required.');
                }

                $categoryName = trim((string) $this->value($row, ['Category', 'category'], 'Uncategorised'));
                $categoryName = $categoryName !== '' ? $categoryName : 'Uncategorised';
                $category = Category::firstOrCreate(
                    ['slug' => Str::slug($categoryName)],
                    ['name' => $categoryName]
                );

                $status = strtolower(trim((string) $this->value($row, ['Status', 'status'], 'active')));

                Product::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                        'slug' => Str::slug($name.'-'.$sku),
                        'price' => $this->number($this->value($row, ['Price', 'price'], 0)),
                        'stock' => $this->integer($this->value($row, ['Stock', 'stock'], 0)),
                        'description' => $this->value($row, ['Description', 'description']),
                        'material' => $this->value($row, ['Material', 'material']),
                        'is_active' => $status !== 'inactive',
                    ]
                );

                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }

        return ['imported' => $imported, 'errors' => $errors, 'total' => count($rows)];
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

    private function number(mixed $value): float
    {
        $value = trim((string) $value);

        return $value === '' ? 0 : (float) str_replace(',', '', $value);
    }

    private function integer(mixed $value): int
    {
        $value = trim((string) $value);

        return $value === '' ? 0 : (int) $value;
    }
}
