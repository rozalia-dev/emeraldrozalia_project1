<?php

namespace App\Services;

use App\Models\TryOnAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http,Storage};
use Illuminate\Support\Str;
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
        if (!in_array($extension, array_merge(['zip'], self::IMAGE_EXTENSIONS, self::MODEL_EXTENSIONS), true)) {
            throw ValidationException::withMessages(['asset' => 'Use ZIP, PNG, JPG, WebP, GLB or USDZ files only.']);
        }
        $directory = 'tryons/'.$uuid.'/'.Str::lower(Str::random(12));
        try {
            return $extension === 'zip'
                ? $this->storeZip($upload, $directory)
                : $this->storeSingle($upload, $directory, $extension);
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($directory);
            throw $e;
        }
    }

    private function storeSingle(UploadedFile $upload, string $directory, string $extension): array
    {
        $data = file_get_contents($upload->getRealPath());
        if (!is_string($data) || $data === '') {
            throw ValidationException::withMessages(['asset'=>'The uploaded try-on asset could not be read.']);
        }
        $files = [];
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $files = array_merge($files, $this->storeTryOnImage($data, $directory, $extension));
        } else {
            $files['model'] = $this->storeModel($data, $directory, $extension);
        }
        return ['files'=>$files,'bytes'=>$this->sizeOf($files),'directory'=>$directory];
    }

    private function storeZip(UploadedFile $upload, string $directory): array
    {
        $zip = new ZipArchive();
        if ($zip->open($upload->getRealPath()) !== true) {
            throw ValidationException::withMessages(['asset'=>'The ZIP archive could not be opened.']);
        }
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
                    $imageFiles = $this->storeTryOnImage($data, $directory, $ext);
                    $preview = $imageFiles['preview'];
                    $source = $imageFiles['source'] ?? null;
                } elseif (in_array($ext, self::MODEL_EXTENSIONS, true) && !$model) {
                    $model = $this->storeModel($data, $directory, $ext);
                }
            }
            if (!$preview && !$model) {
                throw ValidationException::withMessages(['asset'=>'The ZIP did not contain a usable try-on asset.']);
            }
            $files = array_filter(['preview'=>$preview,'source'=>$source ?? null,'model'=>$model]);
            return ['files'=>$files,'bytes'=>$this->sizeOf($files),'directory'=>$directory];
        } finally {
            $zip->close();
        }
    }

    public function reprocessPreview(TryOnAsset $asset): array
    {
        $files = $asset->files ?? [];
        $sourcePath = data_get($files, 'source') ?: data_get($files, 'preview');
        if (!is_string($sourcePath) || !Storage::disk('local')->exists($sourcePath)) {
            throw ValidationException::withMessages(['asset'=>'The original Try-On image is unavailable. Upload the product image again to rebuild the transparent overlay.']);
        }
        $data = Storage::disk('local')->get($sourcePath);
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['asset'=>'The stored Try-On source is not a supported image. Upload PNG, JPG or WebP instead.']);
        }
        $directory = dirname($sourcePath);
        $transparent = $this->hasUsefulTransparency($data) ? $data : $this->removeBackgroundWithFashn($data, $extension);
        $preview = $this->storeImage($transparent, $directory, 'png');
        $oldPreview = data_get($files, 'preview');
        $files['preview'] = $preview;
        if (!data_get($files, 'source')) $files['source'] = $sourcePath;
        if (is_string($oldPreview) && $oldPreview !== $preview && $oldPreview !== $sourcePath) Storage::disk('local')->delete($oldPreview);
        return ['files'=>$files,'bytes'=>$this->sizeOf($files)];
    }

    private function storeTryOnImage(string $data, string $directory, string $extension): array
    {
        $sourceExt = $extension === 'jpeg' ? 'jpg' : $extension;
        $sourcePath = $directory.'/source.'.$sourceExt;
        Storage::disk('local')->put($sourcePath, $data);

        if ($this->hasUsefulTransparency($data)) {
            return ['source'=>$sourcePath,'preview'=>$this->storeImage($data, $directory, $extension)];
        }

        $transparent = $this->removeBackgroundWithFashn($data, $extension);
        return ['source'=>$sourcePath,'preview'=>$this->storeImage($transparent, $directory, 'png')];
    }

    private function hasUsefulTransparency(string $data): bool
    {
        $image = @imagecreatefromstring($data);
        if (!$image) return false;
        $width = imagesx($image);
        $height = imagesy($image);
        $stepX = max(1, intdiv($width, 40));
        $stepY = max(1, intdiv($height, 40));
        for ($y=0; $y<$height; $y+=$stepY) {
            for ($x=0; $x<$width; $x+=$stepX) {
                if ((imagecolorat($image,$x,$y) & 0x7F000000) >> 24 > 8) {
                    imagedestroy($image);
                    return true;
                }
            }
        }
        imagedestroy($image);
        return false;
    }

    private function removeBackgroundWithFashn(string $data, string $extension): string
    {
        $key = (string) config('services.fashion_ai.key');
        $runUrl = rtrim((string) config('services.fashion_ai.url', 'https://api.fashn.ai/v1/run'), '/');
        if ($key === '') {
            throw ValidationException::withMessages(['asset'=>'AI background removal is not configured. Configure the existing FASHN API key, or upload a transparent PNG overlay.']);
        }
        $mime = match ($extension) {
            'jpg','jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        $response = Http::withToken($key)->acceptJson()->timeout(30)->post($runUrl, [
            'model_name'=>'background-remove',
            'inputs'=>[
                'image'=>'data:'.$mime.';base64,'.base64_encode($data),
                'return_base64'=>true,
            ],
        ]);
        if (!$response->successful() || !$response->json('id')) {
            throw ValidationException::withMessages(['asset'=>'AI background removal could not start. Please retry or upload a transparent PNG overlay.']);
        }
        $predictionId = (string) $response->json('id');
        $statusUrl = preg_replace('~/run/?$~','/status/'.$predictionId,$runUrl);
        for ($attempt=0; $attempt<12; $attempt++) {
            usleep(500000);
            $status = Http::withToken($key)->acceptJson()->timeout(20)->get($statusUrl);
            if (!$status->successful()) continue;
            if ($status->json('status') === 'failed') {
                throw ValidationException::withMessages(['asset'=>'AI background removal failed. Please retry or upload a transparent PNG overlay.']);
            }
            if ($status->json('status') !== 'completed') continue;
            $output = $status->json('output.0');
            if (!is_string($output) || !str_starts_with($output,'data:image/png;base64,')) break;
            $decoded = base64_decode(substr($output,strlen('data:image/png;base64,')), true);
            if (is_string($decoded) && $decoded !== '') return $decoded;
            break;
        }
        throw ValidationException::withMessages(['asset'=>'AI background removal timed out. Please retry or upload a transparent PNG overlay.']);
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
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 8);
        } elseif ($outputExt === 'webp' && function_exists('imagewebp')) {
            imagewebp($image, null, 88);
        } else {
            imagejpeg($image, null, 90);
            $path = $directory.'/overlay.jpg';
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
