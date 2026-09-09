<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SpinFrames
{
    // No archive extraction: read bounded entries, decode, and re-encode into private storage.
    public function store(UploadedFile $upload): array
    {
        $zip = new ZipArchive();
        if ($zip->open($upload->getRealPath()) !== true) $this->invalid('Upload a valid ZIP archive.');
        $directory = 'spins/'.Str::uuid();
        try {
            if ($zip->numFiles > 150) $this->invalid('Archive contains too many entries (maximum 150).');
            $entries = []; $total = 0;
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (str_starts_with($name,'/') || str_contains($name,'..') || str_contains($name,'\\') || str_contains($name,"\0") || preg_match('/^[a-z]:/i',$name)) $this->invalid('Unsafe archive path.');
                if (str_ends_with($name,'/') || str_starts_with($name,'__MACOSX/') || basename($name)==='.DS_Store') continue;
                if (!preg_match('/\.(jpe?g|png)$/i',$name)) $this->invalid('The archive must contain JPG or PNG frames only.');
                if ($stat['size'] > 8388608 || ($total += $stat['size']) > 104857600) $this->invalid('Unpacked frames exceed the safe size limit (8 MB each, 100 MB total).');
                $entries[$name] = $i;
            }
            if (count($entries)<2 || count($entries)>72) $this->invalid('Upload between 2 and 72 frames; 24–72 evenly spaced frames are recommended.');
            uksort($entries,'strnatcasecmp');
            $frames=[]; $bytes=0; $dimensions=null;
            foreach ($entries as $name=>$i) {
                $data = $zip->getFromIndex($i,8388609);
                $size = $data === false ? false : @getimagesizefromstring($data);
                if (!$size || !in_array($size[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true) || $size[0]*$size[1]>12000000 || max($size[0],$size[1])>6000) $this->invalid('Each frame must be a valid JPG/PNG image up to 12 megapixels.');
                $shape = [$size[0],$size[1]];
                if ($dimensions && $shape !== $dimensions) $this->invalid('All frames must have identical dimensions.');
                $dimensions = $shape;
                $source = @imagecreatefromstring($data);
                if (!$source) $this->invalid('Could not decode a frame.');
                $ratio=min(1,2000/max($shape)); $width=(int)round($shape[0]*$ratio); $height=(int)round($shape[1]*$ratio);
                $output=imagecreatetruecolor($width,$height);
                imagefill($output,0,0,imagecolorallocate($output,255,255,255));
                imagecopyresampled($output,$source,0,0,0,0,$width,$height,$shape[0],$shape[1]);
                ob_start(); imagejpeg($output,null,85); $encoded=ob_get_clean();
                imagedestroy($source); imagedestroy($output);
                $path=$directory.'/'.sprintf('%03d',count($frames)).'.jpg';
                if (!Storage::disk('local')->put($path,$encoded)) throw new \RuntimeException('Frame storage failed.');
                $frames[]=$path; $bytes+=strlen($encoded);
            }
            return compact('frames','bytes','directory')+['resolution'=>$width.' × '.$height];
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($directory);
            throw $e;
        } finally { $zip->close(); }
    }
    private function invalid(string $message): never { throw ValidationException::withMessages(['archive'=>$message]); }
}
