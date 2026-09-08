<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Product, ProductMedia, ProductVideo, VideoPlay};
use App\Services\AuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class VideoController extends Controller
{
    private function filtered(Request $request): Builder
    {
        $query = ProductVideo::with('product')->withCount(['plays' => fn ($q) => $q->where('day', '>=', now()->subDays(29)->toDateString())])
            ->withSum(['plays' => fn ($q) => $q->where('day', '>=', now()->subDays(29)->toDateString())], 'seconds');
        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('metadata->title', 'ilike', '%'.$search.'%')
                    ->orWhere('alt_text', 'ilike', '%'.$search.'%')
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'ilike', '%'.$search.'%')->orWhere('sku', 'ilike', '%'.$search.'%'));
            });
        }
        if ($request->filled('product_id')) $query->where('product_id', $request->integer('product_id'));
        if (array_key_exists((string) $request->input('category', ''), ProductVideo::CATEGORIES)) {
            $category = $request->input('category');
            $query->where(fn ($q) => $q->where('metadata->category', $category)->when($category === 'product', fn ($q) => $q->orWhereNull('metadata->category')));
        }
        if (in_array($request->input('platform'), ['Website','YouTube','Vimeo'], true)) {
            $platform = $request->input('platform');
            $query->where(fn ($q) => $q->where('metadata->platform', $platform)->when($platform === 'Website', fn ($q) => $q->orWhereNull('metadata->platform')));
        }
        if ($request->input('status') === 'draft') $query->where('active', false);
        if ($request->input('status') === 'scheduled') $query->where('active', true)->where('metadata->publish_at', '>', now()->toIso8601String());
        if ($request->input('status') === 'published') {
            $query->where('active', true)->where(fn ($q) => $q->whereNull('metadata->publish_at')->orWhere('metadata->publish_at', '<=', now()->toIso8601String()));
        }
        return $query;
    }

    public function index(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:150','product_id'=>'nullable|integer','category'=>'nullable|string','platform'=>'nullable|string','status'=>'nullable|string']);
        $perPage = in_array($request->integer('per_page'), [8,16,32], true) ? $request->integer('per_page') : 8;
        $videos = $this->filtered($request)->latest()->paginate($perPage)->withQueryString();
        $all = ProductVideo::with('product')->get();
        $plays = VideoPlay::whereIn('product_media_id', $all->modelKeys())->where('day', '>=', now()->subDays(29)->toDateString());
        $stats = [
            'total' => $all->count(), 'products' => $all->pluck('product_id')->unique()->count(),
            'views' => (clone $plays)->count(), 'seconds' => (int) (clone $plays)->sum('seconds'),
            'bytes' => $all->sum(fn ($v) => (int) data_get($v->metadata, 'bytes', 0)),
            'published' => $all->filter(fn ($v) => $v->video_status === 'published')->count(),
            'scheduled' => $all->filter(fn ($v) => $v->video_status === 'scheduled')->count(),
            'private' => $all->filter(fn ($v) => data_get($v->metadata, 'visibility', $v->disk === 'public' ? 'public' : 'private') === 'private')->count(),
        ];
        $types = collect(ProductVideo::CATEGORIES)->map(fn ($label, $key) => $all->filter(fn ($v) => data_get($v->metadata, 'category', 'product') === $key)->count());
        $top = ProductVideo::with('product')->withCount(['plays' => fn ($q) => $q->where('day', '>=', now()->subDays(29)->toDateString())])->orderByDesc('plays_count')->limit(5)->get();
        $products = Product::orderBy('name')->get(['id','name','sku']);
        return view('admin.videos.index', compact('videos','stats','types','top','products'));
    }

    private function validated(Request $request, ?ProductVideo $video = null): array
    {
        $data = $request->validate([
            'title'=>'required|string|max:160', 'product_id'=>'required|integer',
            'category'=>['required',Rule::in(array_keys(ProductVideo::CATEGORIES))],
            'platform'=>['required',Rule::in(['Website','YouTube','Vimeo'])],
            'status'=>['required',Rule::in(['draft','published','scheduled'])],
            'publish_at'=>'nullable|required_if:status,scheduled|date',
            'visibility'=>['required',Rule::in(['public','private'])],
            'description'=>'nullable|string|max:3000', 'seo_title'=>'nullable|string|max:160',
            'tags'=>'nullable|string|max:500', 'gallery'=>'nullable|boolean', 'allow_download'=>'nullable|boolean',
            'external_url'=>'nullable|url:https|max:500',
            'file'=>'nullable|file|max:20480|mimetypes:video/mp4,video/webm,video/quicktime',
            'poster'=>'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'captions'=>'nullable|file|max:512',
            'caption_language'=>['nullable',Rule::in(['en','ga','fr','de','es'])],
            'duration'=>'nullable|numeric|min:0|max:86400',
            'resolution'=>['nullable','regex:/^[0-9]{2,5} x [0-9]{2,5}$/'],
        ]);
        if (!Product::whereKey($data['product_id'])->exists()) throw ValidationException::withMessages(['product_id'=>'Choose a product in the current company.']);
        if ($data['status'] === 'scheduled' && !Carbon::parse($data['publish_at'])->isFuture()) throw ValidationException::withMessages(['publish_at'=>'Choose a future publication date and time.']);
        if ($data['platform'] === 'Website' && !$request->hasFile('file') && (!$video || $video->platform !== 'Website')) {
            throw ValidationException::withMessages(['file'=>'Upload an MP4, WebM or MOV video (up to 20 MB).']);
        }
        if ($data['platform'] !== 'Website') {
            $candidate = new ProductVideo(['path'=>$data['external_url'] ?? '', 'metadata'=>['platform'=>$data['platform']]]);
            if (!$candidate->embed_url || ($data['platform'] === 'YouTube' && !str_contains($candidate->embed_url, 'youtube-nocookie.com')) || ($data['platform'] === 'Vimeo' && !str_contains($candidate->embed_url, 'player.vimeo.com'))) {
                throw ValidationException::withMessages(['external_url'=>'Enter a valid public '.$data['platform'].' video URL.']);
            }
            if ($request->hasFile('file')) throw ValidationException::withMessages(['file'=>'Choose either an uploaded video or an external video link.']);
        }
        if ($request->hasFile('captions')) {
            $text = file_get_contents($request->file('captions')->getRealPath());
            if (!preg_match('/^(?:\xEF\xBB\xBF)?WEBVTT(?:\r?\n|$)/', $text) || str_contains($text, "\0")) {
                throw ValidationException::withMessages(['captions'=>'Upload a UTF-8 WebVTT (.vtt) caption file beginning with WEBVTT.']);
            }
        }
        return $data;
    }

    private function save(Request $request, ?ProductVideo $video = null): ProductVideo
    {
        $data = $this->validated($request, $video);
        $newPaths = [];
        $oldPaths = [];
        try {
            return DB::transaction(function () use ($request, $data, $video, &$newPaths, &$oldPaths): ProductVideo {
                $existing = $video !== null;
                if ($existing) $video = ProductVideo::whereKey($video->id)->lockForUpdate()->firstOrFail();
                else $video = new ProductVideo();
                $before = $existing ? $video->toArray() : null;
                $meta = $video->metadata ?? [];
                $oldPath = $video->path;
                $oldDisk = $video->disk;
                foreach (['title','category','description','seo_title','tags','visibility','platform','caption_language','duration','resolution'] as $key) {
                    $meta[$key] = $data[$key] ?? ($meta[$key] ?? null);
                }
                $meta['publish_at'] = $data['status'] === 'scheduled' ? Carbon::parse($data['publish_at'])->utc()->toIso8601String() : null;
                $meta['gallery'] = $request->boolean('gallery');
                $meta['allow_download'] = $request->boolean('allow_download');
                $meta['managed_video'] = true;
                $meta['added_by'] ??= $request->user()->name;
                if ($data['platform'] !== 'Website') {
                    $video->path = $data['external_url'];
                    $video->disk = 'local';
                    $meta['bytes'] = 0;
                    $meta['format'] = 'Embed';
                } elseif ($request->hasFile('file')) {
                    $path = $request->file('file')->store('videos/files', 'local');
                    if (!$path) throw new \RuntimeException('Video storage is unavailable.');
                    $newPaths[] = $path;
                    $video->path = $path;
                    $video->disk = 'local';
                    $meta['bytes'] = $request->file('file')->getSize();
                    $meta['format'] = strtoupper($request->file('file')->extension());
                } elseif ($video->disk === 'public') {
                    // Existing public assets must move behind the authorization gate before privacy changes.
                    if (ProductMedia::where('disk','public')->where('path',$oldPath)->where('id','!=',$video->id)->exists()) {
                        throw ValidationException::withMessages(['file'=>'This legacy file is shared with another record. Upload a separate copy before editing its publishing settings.']);
                    }
                    if (!Storage::disk('public')->exists($oldPath)) throw ValidationException::withMessages(['file'=>'The original file is missing. Upload a replacement.']);
                    $path = 'videos/files/'.Str::uuid().'.'.pathinfo($oldPath, PATHINFO_EXTENSION);
                    $stream = Storage::disk('public')->readStream($oldPath);
                    try { $stored = Storage::disk('local')->put($path, $stream); }
                    finally { if (is_resource($stream)) fclose($stream); }
                    if (!$stored) throw new \RuntimeException('Video storage is unavailable.');
                    $newPaths[] = $path;
                    $video->path = $path;
                    $video->disk = 'local';
                    $meta['bytes'] = Storage::disk('local')->size($path);
                }
                foreach (['poster','captions'] as $asset) {
                    if (!$request->hasFile($asset)) continue;
                    if (!empty($meta[$asset])) $oldPaths[] = ['local',$meta[$asset]];
                    $extension = $asset === 'captions' ? 'vtt' : $request->file($asset)->extension();
                    $path = $request->file($asset)->storeAs('videos/'.$asset, Str::uuid().'.'.$extension, 'local');
                    if (!$path) throw new \RuntimeException('Video storage is unavailable.');
                    $newPaths[] = $path;
                    $meta[$asset] = $path;
                }
                if ($existing && $oldPath !== $video->path && !str_starts_with($oldPath, 'https://')) $oldPaths[] = [$oldDisk,$oldPath];
                $video->fill(['product_id'=>$data['product_id'], 'alt_text'=>$data['title'], 'active'=>$data['status'] !== 'draft', 'metadata'=>$meta]);
                $video->save();
                AuditTrail::record($existing ? 'video.updated' : 'video.created', $video, $before, $video->fresh()->toArray());
                DB::afterCommit(function () use (&$oldPaths): void { foreach ($oldPaths as [$disk,$path]) $this->removeUnreferencedFile($disk,$path); });
                return $video->fresh();
            });
        } catch (\Throwable $error) {
            foreach ($newPaths as $path) Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    private function removeUnreferencedFile(string $disk, string $path): void
    {
        if (!in_array($disk,['local','public'],true) || str_contains($path,'..') || !preg_match('~^(videos|product-media)/~',$path)) return;
        if (ProductMedia::where('disk',$disk)->where('path',$path)->exists()) return;
        Storage::disk($disk)->delete($path);
    }

    public function store(Request $request)
    {
        $video = $this->save($request);
        if ($request->expectsJson()) return response()->json(['message'=>'Video saved.', 'video'=>$video->details()],201);
        return redirect()->route('admin.videos.index')->with('success','Video saved.');
    }

    public function update(Request $request, ProductVideo $video)
    {
        $video = $this->save($request, $video);
        if ($request->expectsJson()) return response()->json(['message'=>'Video updated.', 'video'=>$video->details()]);
        return redirect()->route('admin.videos.index')->with('success','Video updated.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids'=>'required|array|min:1|max:100','ids.*'=>'required|integer|distinct','action'=>['required',Rule::in(['publish','draft','delete'])]]);
        DB::transaction(function () use ($data): void {
            $videos = ProductVideo::whereIn('id',$data['ids'])->lockForUpdate()->get();
            abort_unless($videos->count() === count($data['ids']),422,'Choose only videos in the current company.');
            foreach ($videos as $video) {
                $before = $video->toArray();
                if ($data['action'] === 'delete') {
                    $assets = [[$video->disk,$video->path]];
                    foreach (['poster','captions'] as $key) if ($path = data_get($video->metadata,$key)) $assets[] = ['local',$path];
                    AuditTrail::record('video.deleted',$video,$before,null);
                    $video->delete();
                    DB::afterCommit(function () use ($assets): void { foreach ($assets as [$disk,$path]) $this->removeUnreferencedFile($disk,$path); });
                } else {
                    // Bulk status changes never change privacy.
                    $meta = $video->metadata ?? [];
                    $meta['publish_at'] = null;
                    $video->update(['active'=>$data['action'] === 'publish','metadata'=>$meta]);
                    AuditTrail::record('video.'.$data['action'],$video,$before,$video->fresh()->toArray());
                }
            }
        });
        return $request->expectsJson() ? response()->json(['message'=>'Selected videos updated.']) : back()->with('success','Selected videos updated.');
    }

    public function destroy(ProductVideo $video, Request $request)
    {
        $request->merge(['ids'=>[$video->id],'action'=>'delete']);
        return $this->bulk($request);
    }

    public function audit(ProductVideo $video)
    {
        $entries = AuditLog::whereIn('subject_type', [ProductVideo::class, ProductMedia::class])->where('subject_id', (string) $video->id)->latest('created_at')->limit(50)->get(['action','created_at','user_id','before','after']);
        return response()->json(['uuid'=>$video->uuid,'entries'=>$entries]);
    }

    public function export(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:150','product_id'=>'nullable|integer','category'=>'nullable|string','platform'=>'nullable|string','status'=>'nullable|string']);
        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output','w');
            fputcsv($out,['UUID','Title','Product','SKU','Category','Status','Visibility','Platform','Views (30 days)','Watch seconds (30 days)'],',','"','');
            foreach ($this->filtered($request)->orderBy('id')->cursor() as $video) {
                $values = [$video->uuid,$video->title,$video->product?->name,$video->product?->sku,data_get($video->metadata,'category','product'),$video->video_status,data_get($video->metadata,'visibility','private'),$video->platform,$video->plays_count,$video->plays_sum_seconds ?? 0];
                fputcsv($out,array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/',(string)$v) ? "'".$v : $v,$values),',','"','');
            }
            fclose($out);
        },'videos-'.now()->format('Y-m-d').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
