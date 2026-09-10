<?php

namespace App\Http\Controllers;

use App\Models\{ProductVideo, VideoPlay};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class VideoPlaybackController extends Controller
{
    private function authorizeVideo(ProductVideo $video): void
    {
        abort_unless((auth()->user()?->is_admin && $video->product) || $video->isPubliclyPlayable(),404);
    }

    public function watch(ProductVideo $video)
    {
        $this->authorizeVideo($video);
        return view('site.video',compact('video'));
    }

    public function asset(ProductVideo $video, string $asset, Request $request)
    {
        $this->authorizeVideo($video);
        abort_unless(in_array($asset,['video','poster','captions'],true),404);
        if ($asset === 'video') abort_unless($video->platform === 'Website',404);
        $path = $asset === 'video' ? $video->path : data_get($video->metadata,$asset);
        $disk = $asset === 'video' ? $video->disk : 'local';
        abort_unless(is_string($path) && !str_contains($path,'..') && !str_starts_with($path,'/') && in_array($disk,['local','public'],true),404);
        abort_unless(Storage::disk($disk)->exists($path),404);
        if ($request->boolean('download')) abort_unless($asset === 'video' && (auth()->user()?->is_admin || data_get($video->metadata,'allow_download',false)),403);
        $headers = ['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'];
        if ($asset === 'captions') $headers['Content-Type'] = 'text/vtt; charset=UTF-8';
        $response = response()->file(Storage::disk($disk)->path($path),$headers);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->setContentDisposition($request->boolean('download') ? 'attachment' : 'inline');
        return $response;
    }

    public function record(Request $request, ProductVideo $video)
    {
        abort_unless($video->isPubliclyPlayable() && $video->platform === 'Website',404);
        $data = $request->validate(['seconds'=>'required|integer|min:0|max:15']);
        // Preview sessions are deliberately excluded from customer metrics.
        if ($request->user()?->is_admin) return response()->noContent();
        $client = $request->session()->getId();
        $headerClient = trim((string) $request->header('X-Video-Client'));
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $headerClient)) {
            $client = $headerClient;
        }
        $hash = hash_hmac('sha256',$client,config('app.key'));
        $day = now()->toDateString();
        $now = now()->utc();
        VideoPlay::query()->insertOrIgnore([
            'product_media_id'=>$video->id,'session_hash'=>$hash,'day'=>$day,
            'started_at'=>$now,'seconds'=>0,'created_at'=>$now,'updated_at'=>$now,
        ]);
        DB::transaction(function () use ($video,$hash,$day,$data,$now): void {
            $play = VideoPlay::where('product_media_id',$video->id)->where('session_hash',$hash)->where('day',$day)->lockForUpdate()->firstOrFail();
            $timestamps = DB::table('video_plays')->where('id', $play->id)->first(['created_at', 'updated_at']);
            $initialHeartbeat = (int) $play->seconds === 0
                && $timestamps
                && substr((string) $timestamps->created_at, 0, 19) === substr((string) $timestamps->updated_at, 0, 19);
            $lastUpdated = $timestamps ? \Illuminate\Support\Carbon::parse((string) $timestamps->updated_at, 'UTC') : now()->utc();
            $elapsed = $initialHeartbeat ? 0 : max(0, $lastUpdated->diffInSeconds(now()->utc(), false));
            $increment = min((int)$data['seconds'], $elapsed, 15);
            $seconds = min(86400,$play->seconds + $increment);
            // Persist UTC explicitly; PostgreSQL timestamp-with-time-zone values must
            // not be rewritten as local Dublin wall time without an offset.
            VideoPlay::whereKey($play->id)->update(['seconds'=>$seconds,'updated_at'=>$now]);
        });
        return response()->noContent();
    }

    public function sitemap()
    {
        $videos = ProductVideo::query()
            ->where('active',true)
            ->whereNotNull('uuid')
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($videos as $video) {
            try {
                if (!$video->isPubliclyPlayable()) continue;

                $xml .= '    <url><loc>'.e(route('videos.watch',['video'=>$video->uuid])).'</loc>';
                $lastmod = $video->updated_at ?: $video->created_at;
                if ($lastmod) {
                    $xml .= '<lastmod>'.e(\Illuminate\Support\Carbon::parse($lastmod)->toAtomString()).'</lastmod>';
                }
                $xml .= "</url>\n";
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $xml .= '</urlset>'."\n";

        return response($xml,200,['Content-Type'=>'application/xml; charset=UTF-8']);
    }
}
