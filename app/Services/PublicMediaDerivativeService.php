<?php

namespace App\Services;

use App\Models\{MediaAsset, ProductMedia, VariantMedia};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PublicMediaDerivativeService
{
    /**
     * Generate safe, isolated image derivatives. Unsupported image codecs and
     * non-image media remain valid; they simply keep the original route.
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

        $dimensions = @getimagesize(Storage::disk($disk)->path($sourcePath));
        $sourceWidth = (int) ($dimensions[0] ?? 0);
        $sourceHeight = (int) ($dimensions[1] ?? 0);
        if ($sourceWidth < 2 || $sourceHeight < 2) {
            return [];
        }

        $sourcePathAbsolute = Storage::disk($disk)->path($sourcePath);
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
                $canvas = imagecreatetruecolor($width, $height);
                if (! $canvas) {
                    continue;
                }
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
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($source);
        }

        return $variants;
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
