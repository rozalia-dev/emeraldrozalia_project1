<?php

namespace App\Services;

use App\Models\{MediaAsset, ProductMedia, VariantMedia};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PublicMediaDerivativeService
{
    private const MEMORY_RESERVE_BYTES = 16 * 1024 * 1024;

    /**
     * GD commonly needs more than the raw RGBA bitmap size while decoding and
     * resampling. Keep the estimate deliberately conservative so derivative
     * generation degrades gracefully instead of exhausting the PHP worker.
     */
    private const GD_MEMORY_MULTIPLIER = 2.5;

    /**
     * Generate safe, isolated image derivatives. Unsupported image codecs,
     * constrained workers and non-image media remain valid; they simply keep
     * the original route.
     *
     * @return array<string, array{path: string, width: int, height: int}>
     */
    public function generate(MediaAsset|ProductMedia|VariantMedia $record, ?int $version = null): array
    {
        if (! str_starts_with((string) $record->mime_type, 'image/')) {
            return [];
        }

        $disk = (string) ($record->disk ?: 'local');
        $sourcePath = (string) $record->path;
        if (! in_array($disk, ['local', 'public'], true)
            || $sourcePath === ''
            || str_starts_with($sourcePath, '/')
            || str_contains($sourcePath, '..')
            || ! Storage::disk($disk)->exists($sourcePath)
            || ! function_exists('imagewebp')
            || ! function_exists('imagecreatetruecolor')
            || ! function_exists('imagecopyresampled')) {
            return [];
        }

        $sourcePathAbsolute = Storage::disk($disk)->path($sourcePath);
        $dimensions = @getimagesize($sourcePathAbsolute);
        $sourceWidth = (int) ($dimensions[0] ?? 0);
        $sourceHeight = (int) ($dimensions[1] ?? 0);
        if ($sourceWidth < 2 || $sourceHeight < 2) {
            return [];
        }

        $sourceMemory = $this->estimatedBitmapBytes($sourceWidth, $sourceHeight);
        if (! $this->hasMemoryHeadroom($sourceMemory)) {
            return [];
        }

        $source = $this->createSource($sourcePathAbsolute, (string) $record->mime_type, $dimensions);
        if (! $source) {
            return [];
        }

        $variants = [];
        try {
            foreach ([480, 768, 1200] as $width) {
                if ($width >= $sourceWidth) {
                    continue;
                }

                $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
                $canvasMemory = $this->estimatedBitmapBytes($width, $height);

                // memory_get_usage() is not consistent across GD builds about
                // native image allocations, so include the decoded source
                // estimate as an additional safety margin for every canvas.
                if (! $this->hasMemoryHeadroom($sourceMemory + $canvasMemory)) {
                    continue;
                }

                $canvas = imagecreatetruecolor($width, $height);
                if (! $canvas) {
                    continue;
                }

                try {
                    if (function_exists('imagealphablending')) {
                        imagealphablending($canvas, false);
                    }
                    if (function_exists('imagesavealpha')) {
                        imagesavealpha($canvas, true);
                    }
                    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

                    $namespace = $version && $version > 0 ? 'v'.$version : 'current';
                    $path = 'media-managed/derivatives/'.($record->uuid ?: Str::uuid()->toString()).'/'.$namespace.'/'.$width.'.webp';
                    $absolutePath = Storage::disk($disk)->path($path);
                    $directory = dirname($absolutePath);
                    if (! is_dir($directory)) {
                        @mkdir($directory, 0755, true);
                    }
                    if (imagewebp($canvas, $absolutePath, 82) && Storage::disk($disk)->exists($path)) {
                        $variants[(string) $width] = [
                            'path' => $path,
                            'width' => $width,
                            'height' => $height,
                        ];
                    }
                } finally {
                    imagedestroy($canvas);
                }
            }
        } finally {
            imagedestroy($source);
        }

        return $variants;
    }

    private function estimatedBitmapBytes(int $width, int $height): int
    {
        $rawBytes = max(1, $width) * max(1, $height) * 4;

        return (int) ceil($rawBytes * self::GD_MEMORY_MULTIPLIER);
    }

    private function hasMemoryHeadroom(int $additionalBytes): bool
    {
        $limit = $this->memoryLimitBytes();
        if ($limit === PHP_INT_MAX) {
            return true;
        }

        $used = max(memory_get_usage(true), memory_get_usage(false));

        return $used + max(0, $additionalBytes) + self::MEMORY_RESERVE_BYTES < $limit;
    }

    private function memoryLimitBytes(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }

        if (! preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMGTP]?)B?$/i', $value, $matches)) {
            return PHP_INT_MAX;
        }

        $number = (float) $matches[1];
        $power = match (strtoupper($matches[2] ?? '')) {
            'K' => 1,
            'M' => 2,
            'G' => 3,
            'T' => 4,
            'P' => 5,
            default => 0,
        };
        $bytes = $number * (1024 ** $power);

        return $bytes >= PHP_INT_MAX ? PHP_INT_MAX : (int) $bytes;
    }

    private function createSource(string $path, string $mime, array|false $dimensions): mixed
    {
        $detectedMime = is_array($dimensions) && isset($dimensions['mime']) ? (string) $dimensions['mime'] : '';
        $factory = match (strtolower($mime)) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/gif' => 'imagecreatefromgif',
            'image/webp' => 'imagecreatefromwebp',
            default => match (strtolower($detectedMime)) {
                'image/jpeg' => 'imagecreatefromjpeg',
                'image/png' => 'imagecreatefrompng',
                'image/gif' => 'imagecreatefromgif',
                'image/webp' => 'imagecreatefromwebp',
                default => null,
            },
        };

        return $factory && function_exists($factory) ? @$factory($path) : null;
    }
}
