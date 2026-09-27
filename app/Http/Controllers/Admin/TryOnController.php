<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog,Product,TryOnAsset,TryOnVisit};
use App\Services\{AuditTrail,TryOnFiles};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TryOnController extends Controller
{
    private function filtered(Request $request)
    {
        $request->validate([
            'q'=>'nullable|string|max:150',
            'product_id'=>'nullable|integer',
            'status'=>['nullable',Rule::in(array_keys(TryOnAsset::STATUSES))],
            'type'=>['nullable',Rule::in(array_keys(TryOnAsset::TYPES))],
            'device'=>'nullable|in:mobile,desktop',
        ]);
        $query = TryOnAsset::with('product');
        if ($term = trim((string) $request->input('q'))) {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn ($query) => $query->where('title',$like,'%'.$term.'%')
                ->orWhereHas('product', fn ($product) => $product->where('name',$like,'%'.$term.'%')->orWhere('sku',$like,'%'.$term.'%')));
        }
        foreach (['product_id','status','type'] as $field) {
            if ($request->filled($field)) $query->where($field, $request->input($field));
        }
        if ($request->input('device') === 'mobile') $query->where('settings->mobile', true);
        if ($request->input('device') === 'desktop') $query->where('settings->mobile', false);
        return $query;
    }

    public function index(Request $request)
    {
        $perPage = in_array($request->integer('per_page'), [8,20,40], true) ? $request->integer('per_page') : 8;
        $assets = $this->filtered($request)
            ->withCount(['visits'=>fn ($visits) => $visits->where('day','>=',now()->subDays(29)->toDateString())])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $all = TryOnAsset::get();
        $visits = TryOnVisit::whereIn('try_on_asset_id', $all->modelKeys())->where('day','>=',now()->subDays(29)->toDateString());
        $visitCount = (clone $visits)->count();
        $converted = (clone $visits)->where('converted', true)->count();
        $stats = [
            'total'=>$all->count(),
            'bytes'=>$all->sum('bytes'),
            'tryons'=>$visitCount,
            'unique'=>(clone $visits)->distinct()->count('visitor_hash'),
            'converted'=>$converted,
            'conversion_rate'=>$visitCount ? (100 * $converted / $visitCount) : 0,
            'session_seconds'=>(float) ((clone $visits)->where('session_seconds','>',0)->avg('session_seconds') ?? 0),
        ];
        foreach (TryOnAsset::STATUSES as $key=>$label) $stats[$key] = $all->where('status',$key)->count();

        $devices = [];
        foreach (['mobile_ar','desktop_web','ios_app','android_app'] as $device) {
            $devices[$device] = (clone $visits)->where('device',$device)->count();
        }

        $products = Product::orderBy('name')->get(['id','name','sku']);
        $scopedProductId = $request->filled('product_id') ? $request->integer('product_id') : null;
        $scopedProduct = $scopedProductId ? $products->firstWhere('id', $scopedProductId) : null;
        $selected = $request->filled('edit') ? TryOnAsset::findOrFail($request->integer('edit')) : $assets->first();
        $statuses = TryOnAsset::STATUSES;
        $types = TryOnAsset::TYPES;
        $targets = TryOnAsset::TARGETS;
        $pageStart = max(1, $assets->currentPage()-2);
        $pageEnd = min($assets->lastPage(), $assets->currentPage()+2);

        $deviceTotal = max(1, array_sum($devices));
        $angleMobile = round(360 * ($devices['mobile_ar'] ?? 0) / $deviceTotal, 2);
        $angleDesktop = round($angleMobile + 360 * ($devices['desktop_web'] ?? 0) / $deviceTotal, 2);
        $angleIos = round($angleDesktop + 360 * ($devices['ios_app'] ?? 0) / $deviceTotal, 2);

        return view('admin.tryons.index', compact(
            'assets','stats','devices','products','selected','statuses','types','targets',
            'pageStart','pageEnd','angleMobile','angleDesktop','angleIos','scopedProductId','scopedProduct'
        ));
    }

    private function save(Request $request, ?TryOnAsset $asset=null)
    {
        $data = $request->validate([
            'product_id'=>'required|integer',
            'title'=>'required|string|max:160',
            'type'=>['required',Rule::in(array_keys(TryOnAsset::TYPES))],
            'target'=>['required',Rule::in(array_keys(TryOnAsset::TARGETS))],
            'age_range'=>'nullable|string|max:40',
            'status'=>['required',Rule::in(array_keys(TryOnAsset::STATUSES))],
            'visibility'=>'required|in:public,private',
            'asset'=>[$asset?'nullable':'required','file','max:20480'],
            'alt'=>'nullable|string|max:500',
            'seo_title'=>'nullable|string|max:160',
            'tags'=>'nullable|string|max:500',
            'auto_fit'=>'nullable|boolean',
            'face_detection'=>'nullable|boolean',
            'realistic_lighting'=>'nullable|boolean',
            'shadow_rendering'=>'nullable|boolean',
            'occlusion'=>'nullable|boolean',
            'high_quality'=>'nullable|boolean',
            'mobile'=>'nullable|boolean',
            'model_scale'=>'nullable|numeric|between:0.2,3',
            'model_y'=>'nullable|numeric|between:-2,2',
            'model_rotation'=>'nullable|numeric|between:-180,180',
        ]);
        Product::findOrFail($data['product_id']);

        $uuid = $asset?->uuid ?: (string) Str::uuid();
        $stored = $request->hasFile('asset') ? app(TryOnFiles::class)->store($request->file('asset'), $uuid) : null;
        try {
            DB::transaction(function () use ($request,$data,$stored,$uuid,&$asset) {
                $exists = $asset !== null;
                $asset = $exists ? TryOnAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail() : new TryOnAsset(['uuid'=>$uuid]);
                $before = $exists ? $asset->toArray() : null;
                $oldFiles = $asset->files ?? [];
                $files = $stored['files'] ?? $oldFiles;

                if ($data['status'] === 'published' && !is_string(data_get($files,'preview'))) {
                    throw ValidationException::withMessages(['asset'=>'Published try-on assets require a PNG, JPG or WebP preview overlay. A GLB/USDZ-only asset may remain Draft or In Review.']);
                }

                $settings = [];
                foreach (TryOnAsset::DEFAULTS as $key=>$default) $settings[$key] = is_bool($default) ? $request->boolean($key) : $default;
                foreach (['model_scale','model_y','model_rotation'] as $key) if ($request->filled($key)) $settings[$key] = (float) $request->input($key);
                $tags = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($tag) => trim($tag))->filter()->unique()->take(20)->values()->all();

                $asset->fill(array_intersect_key($data, array_flip(['product_id','title','type','target','age_range','status','visibility'])));
                $asset->fill([
                    'uuid'=>$uuid,
                    'files'=>$files,
                    'settings'=>$settings,
                    'seo'=>[
                        'alt'=>$data['alt'] ?? $data['title'].' virtual try-on',
                        'title'=>$data['seo_title'] ?? $data['title'].' — Try On',
                        'tags'=>$tags,
                    ],
                    'bytes'=>$stored['bytes'] ?? $asset->bytes ?? 0,
                    'updated_by'=>$request->user()->name,
                ]);
                $asset->save();
                AuditTrail::record($exists?'tryon.updated':'tryon.created', $asset, $before, $asset->toArray());

                if ($stored && $oldFiles) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete(array_values(array_filter($oldFiles))));
                }
            });
        } catch (\Throwable $e) {
            if ($stored) Storage::disk('local')->deleteDirectory($stored['directory']);
            throw $e;
        }
        return redirect()->route('admin.tryons.index',['product_id'=>$asset->product_id,'edit'=>$asset->id])->with('success','Virtual try-on asset saved.');
    }

    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, TryOnAsset $tryon) { return $this->save($request, $tryon); }

    private function deleteAsset(TryOnAsset $asset): void
    {
        $before = $asset->toArray();
        $uuid = $asset->uuid;
        AuditTrail::record('tryon.deleted', $asset, $before, null);
        $asset->delete();
        DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory('tryons/'.$uuid));
    }

    public function destroy(TryOnAsset $tryon)
    {
        DB::transaction(fn () => $this->deleteAsset($tryon));

        return redirect()->route('admin.tryons.index')->with('success','Virtual try-on asset deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'=>'required|array|min:1|max:100',
            'ids.*'=>'integer|distinct',
            'action'=>['required',Rule::in(['published','in_review','draft','needs_attention','archived','delete'])],
        ]);
        DB::transaction(function () use ($data) {
            $assets = TryOnAsset::whereIn('id',$data['ids'])->lockForUpdate()->get();
            abort_unless($assets->count() === count($data['ids']), 422, 'Select try-on assets in the current company.');
            foreach ($assets as $asset) {
                if ($data['action'] === 'delete') {
                    $this->deleteAsset($asset);
                    continue;
                }
                $before = $asset->toArray();
                if ($data['action'] === 'published' && !$asset->previewPath()) {
                    throw ValidationException::withMessages(['ids'=>'A selected asset has no browser preview overlay and cannot be published.']);
                }
                $asset->update(['status'=>$data['action'],'updated_by'=>auth()->user()->name]);
                AuditTrail::record('tryon.'.$data['action'],$asset,$before,$asset->fresh()->toArray());
            }
        });
        return back()->with('success','Selected virtual try-on assets updated.');
    }

    public function generate3d(TryOnAsset $tryon)
    {
        abort_unless(config('services.meshy.key'), 503, 'AI 3D generation is not configured. Add MESHY_API_KEY to the production environment.');
        $preview = $tryon->previewPath();
        abort_unless($preview && Storage::disk('local')->exists($preview), 422, 'Upload and save a product preview image before generating 3D.');

        $bytes = Storage::disk('local')->get($preview);
        $ext = strtolower(pathinfo($preview, PATHINFO_EXTENSION));
        $mime = match ($ext) { 'jpg','jpeg'=>'image/jpeg', 'webp'=>'image/webp', default=>'image/png' };
        $image = 'data:'.$mime.';base64,'.base64_encode($bytes);

        $response = Http::withToken(config('services.meshy.key'))
            ->acceptJson()->timeout(45)
            ->post(rtrim(config('services.meshy.base_url'), '/').'/image-to-3d', [
                'image_url'=>$image,
                'ai_model'=>'latest',
                'should_texture'=>true,
                'image_enhancement'=>true,
                'auto_size'=>true,
                'origin_at'=>'center',
                'target_formats'=>['glb'],
            ]);
        $response->throw();
        $taskId = (string) $response->json('result');
        abort_if($taskId === '', 502, 'AI 3D provider did not return a task ID.');

        $settings = array_replace(TryOnAsset::DEFAULTS, $tryon->settings ?? [], [
            'ai_3d_task_id'=>$taskId,
            'ai_3d_status'=>'PENDING',
            'ai_3d_started_at'=>now()->toIso8601String(),
        ]);
        $tryon->update(['settings'=>$settings,'updated_by'=>auth()->user()->name]);
        AuditTrail::record('tryon.ai3d.started',$tryon,null,['task_id'=>$taskId]);
        return response()->json(['status'=>'PENDING','message'=>'AI 3D generation started.']);
    }

    public function generate3dStatus(TryOnAsset $tryon)
    {
        abort_unless(config('services.meshy.key'), 503, 'AI 3D generation is not configured.');
        $taskId = data_get($tryon->settings, 'ai_3d_task_id');
        abort_unless(is_string($taskId) && $taskId !== '', 404, 'No AI 3D generation task exists for this asset.');

        $response = Http::withToken(config('services.meshy.key'))->acceptJson()->timeout(30)
            ->get(rtrim(config('services.meshy.base_url'), '/').'/image-to-3d/'.rawurlencode($taskId));
        $response->throw();
        $status = strtoupper((string) $response->json('status'));

        if ($status === 'SUCCEEDED' && !$tryon->modelPath()) {
            $url = $response->json('model_urls.glb');
            abort_unless(is_string($url) && str_starts_with($url, 'https://'), 502, 'AI provider completed without a GLB model.');
            $modelResponse = Http::timeout(90)->get($url);
            $modelResponse->throw();
            $stored = app(TryOnFiles::class)->storeGeneratedModel($modelResponse->body(), $tryon->uuid, 'glb');
            $oldFiles = $tryon->files ?? [];
            $oldModel = data_get($oldFiles, 'model');
            $files = array_replace($oldFiles, ['model'=>$stored['path']]);
            $settings = array_replace($tryon->settings ?? [], [
                'ai_3d_status'=>'SUCCEEDED',
                'ai_3d_completed_at'=>now()->toIso8601String(),
                'model_scale'=>data_get($tryon->settings,'model_scale',1),
                'model_y'=>data_get($tryon->settings,'model_y',0),
                'model_rotation'=>data_get($tryon->settings,'model_rotation',0),
            ]);
            $tryon->update(['files'=>$files,'settings'=>$settings,'bytes'=>(int)$tryon->bytes+$stored['bytes'],'updated_by'=>auth()->user()->name]);
            if (is_string($oldModel) && $oldModel !== $stored['path']) Storage::disk('local')->delete($oldModel);
            AuditTrail::record('tryon.ai3d.completed',$tryon,null,['task_id'=>$taskId,'model'=>$stored['path']]);
        } elseif (in_array($status, ['FAILED','CANCELED','EXPIRED'], true)) {
            $settings = array_replace($tryon->settings ?? [], ['ai_3d_status'=>$status]);
            $tryon->update(['settings'=>$settings,'updated_by'=>auth()->user()->name]);
        } else {
            $settings = array_replace($tryon->settings ?? [], ['ai_3d_status'=>$status ?: 'PROCESSING']);
            $tryon->update(['settings'=>$settings]);
        }

        return response()->json([
            'status'=>$status ?: 'PROCESSING',
            'progress'=>(int) $response->json('progress',0),
            'ready'=>$tryon->fresh()->modelPath() !== null,
            'error'=>$response->json('task_error.message'),
        ]);
    }

    public function audit(TryOnAsset $tryon)
    {
        $entries = AuditLog::where('subject_type',TryOnAsset::class)
            ->where('subject_id',(string) $tryon->id)
            ->latest('id')->limit(50)->get(['action','created_at','user_id']);
        return response()->json(['uuid'=>$tryon->uuid,'entries'=>$entries]);
    }

    public function export(Request $request)
    {
        $query = $this->filtered($request);
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output','w');
            fputcsv($out,['UUID','Product','SKU','Title','Type','Target','Age Range','Status','Visibility','Bytes'],',','"','');
            foreach ($query->orderBy('id')->cursor() as $asset) {
                $row = [$asset->uuid,$asset->product?->name,$asset->product?->sku,$asset->title,$asset->type,$asset->target,$asset->age_range,$asset->status,$asset->visibility,$asset->bytes];
                fputcsv($out,array_map(fn ($value) => preg_match('/^[=+\-@\t\r]/',(string) $value) ? "'".$value : $value,$row),',','"','');
            }
            fclose($out);
        },'virtual-try-on-assets.csv',['Content-Type'=>'text/csv']);
    }
}
