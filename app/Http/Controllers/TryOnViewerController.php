<?php

namespace App\Http\Controllers;

use App\Models\{TryOnAsset,TryOnVisit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TryOnViewerController extends Controller
{
    private function authorizeAsset(TryOnAsset $tryon): void
    {
        abort_unless(auth()->user()?->is_admin || $tryon->isPublic(), 404);
    }

    public function asset(TryOnAsset $tryon, string $asset)
    {
        $this->authorizeAsset($tryon);
        abort_unless(in_array($asset, ['preview','model'], true), 404);
        $path = data_get($tryon->files, $asset);
        abort_unless(is_string($path) && $path !== '', 404);
        $quotedUuid = preg_quote($tryon->uuid, '~');
        abort_unless((bool) preg_match('~^tryons/'.$quotedUuid.'/(overlay\.(png|jpg|webp)|model\.(glb|usdz))$~', $path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            'glb' => 'model/gltf-binary',
            'usdz' => 'model/vnd.usdz+zip',
            default => 'application/octet-stream',
        };
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function visit(Request $request, TryOnAsset $tryon)
    {
        abort_unless($tryon->isPublic(), 404);
        $data = $request->validate([
            'device' => 'required|in:mobile_ar,desktop_web,ios_app,android_app',
            'converted' => 'required|boolean',
            'session_seconds' => 'required|integer|between:0,7200',
        ]);
        if ($request->user()?->is_admin) return response()->noContent();
        $hash = hash_hmac('sha256', $request->session()->getId(), config('app.key'));
        $key = [
            'try_on_asset_id' => $tryon->id,
            'visitor_hash' => $hash,
            'day' => now()->toDateString(),
        ];
        TryOnVisit::insertOrIgnore($key + [
            'device' => $data['device'],
            'converted' => $data['converted'],
            'session_seconds' => $data['session_seconds'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TryOnVisit::where($key)->update([
            'device' => $data['device'],
            'converted' => $data['converted'] ? true : \DB::raw('converted'),
            'session_seconds' => \DB::raw('GREATEST(session_seconds, '.(int) $data['session_seconds'].')'),
            'updated_at' => now(),
        ]);
        return response()->noContent();
    }
}
