<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class TryOnFiles
{
    private const IMAGE_EXTENSIONS = ['png','jpg','jpeg','webp'];
    private const MODEL_EXTENSIONS = ['glb','usdz'];
    private const MAX_ARCHIVE_ENTRIES = 20;
    private const MAX_TOTAL_BYTES = 50 * 1024 * 1024;
    private const MAX_DIMENSION = 4096;

    public function store(UploadedFile $upload, string $uuid): array
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        if ($extension === 'zip') {
            return $this->storeZip($upload, $uuid);
        }
        if (!in_array($extension, array_merge(self::IMAGE_EXTENSIONS, self::MODEL_EXTENSIONS), true)) {
            throw ValidationException::withMessages(['asset' => 'Use ZIP, PNG, JPG, WebP, GLB or USDZ files only.']);
        }
        $directory = 'tryons/'.$uuid;
        Storage::disk('local')->deleteDirectory($directory);
        try {
            $files = [];
            if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $files['preview'] = $this->storeImage(file_get_contents($upload->getRealPath()), $directory, $extension);
            } else {
                $files['model'] = $this->storeModel(file_get_contents($upload->getRealPath()), $directory, $extension);
            }
            return ['files'=>$files,'bytes'=>$this->sizeOf($files),'directory'=>$directory];
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($directory);
            throw $e;
        }
    }

    private function storeZip(UploadedFile $upload, string $uuid): array
    {
        $zip = new ZipArchive();
        if ($zip->open($upload->getRealPath()) !== true) {
            throw ValidationException::withMessages(['asset'=>'The ZIP archive could not be opened.']);
        }
        $directory = 'tryons/'.$uuid;
        Storage::disk('local')->deleteDirectory($directory);
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw ValidationException::withMessages(['asset'=>'A ZIP may contain between 1 and '.self::MAX_ARCHIVE_ENTRIES.' supported assets.']);
            }
            $preview = null;
            $model = null;
            $total = 0;
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                    throw ValidationException::withMessages(['asset'=>'Unsafe ZIP paths are not allowed.']);
                }
                if (str_ends_with($name, '/')) continue;
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, array_merge(self::IMAGE_EXTENSIONS, self::MODEL_EXTENSIONS), true)) {
                    throw ValidationException::withMessages(['asset'=>'ZIP archives may contain PNG, JPG, WebP, GLB or USDZ files only.']);
                }
                $size = (int) ($stat['size'] ?? 0);
                $total += $size;
                if ($size <= 0 || $total > self::MAX_TOTAL_BYTES) {
                    throw ValidationException::withMessages(['asset'=>'The unpacked ZIP is too large.']);
                }
                $data = $zip->getFromIndex($i);
                if (!is_string($data) || $data === '') {
                    throw ValidationException::withMessages(['asset'=>'A ZIP entry could not be read.']);
                }
                if (in_array($ext, self::IMAGE_EXTENSIONS, true) && !$preview) {
                    $preview = $this->storeImage($data, $directory, $ext);
                } elseif (in_array($ext, self::MODEL_EXTENSIONS, true) && !$model) {
                    $model = $this->storeModel($data, $directory, $ext);
                }
            }
            if (!$preview && !$model) {
                throw ValidationException::withMessages(['asset'=>'The ZIP did not contain a usable try-on asset.']);
            }
            $files = array_filter(['preview'=>$preview,'model'=>$model]);
            return ['files'=>$files,'bytes'=>$this->sizeOf($files),'directory'=>$directory];
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($directory);
            throw $e;
        } finally {
            $zip->close();
        }
    }

    private function storeImage(string $data, string $directory, string $extension): string
    {
        $info = @getimagesizefromstring($data);
        if (!$info || $info[0] < 32 || $info[1] < 32 || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw ValidationException::withMessages(['asset'=>'Try-on images must be valid and no larger than '.self::MAX_DIMENSION.' × '.self::MAX_DIMENSION.' pixels.']);
        }
        $image = @imagecreatefromstring($data);
        if (!$image) throw ValidationException::withMessages(['asset'=>'The try-on image could not be decoded.']);
        $outputExt = $extension === 'jpeg' ? 'jpg' : $extension;
        if (!in_array($outputExt, ['png','jpg','webp'], true)) $outputExt = 'png';
        $path = $directory.'/overlay.'.$outputExt;
        ob_start();
        if ($outputExt === 'png') {
            imagealphablending($image, false); imagesavealpha($image, true); imagepng($image, null, 8);
        } elseif ($outputExt === 'webp' && function_exists('imagewebp')) {
            imagewebp($image, null, 88);
        } else {
            imagejpeg($image, null, 90); $path = $directory.'/overlay.jpg';
        }
        $encoded = ob_get_clean();
        imagedestroy($image);
        if (!is_string($encoded) || $encoded === '') throw ValidationException::withMessages(['asset'=>'The try-on image could not be optimized.']);
        Storage::disk('local')->put($path, $encoded);
        return $path;
    }

    private function storeModel(string $data, string $directory, string $extension): string
    {
        if (strlen($data) < 32) throw ValidationException::withMessages(['asset'=>'The 3D try-on file is empty or invalid.']);
        if ($extension === 'glb' && substr($data, 0, 4) !== 'glTF') {
            throw ValidationException::withMessages(['asset'=>'The GLB file header is invalid.']);
        }
        if ($extension === 'usdz' && substr($data, 0, 2) !== 'PK') {
            throw ValidationException::withMessages(['asset'=>'The USDZ file header is invalid.']);
        }
        $path = $directory.'/model.'.$extension;
        Storage::disk('local')->put($path, $data);
        return $path;
    }

    private function sizeOf(array $files): int
    {
        return array_sum(array_map(fn ($path) => Storage::disk('local')->exists($path) ? Storage::disk('local')->size($path) : 0, $files));
    }
}
