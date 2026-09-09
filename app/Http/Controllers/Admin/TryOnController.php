<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog,Product,TryOnAsset,TryOnVisit};
use App\Services\{AuditTrail,TryOnFiles};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
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
            $query->where(fn ($query) => $query->where('title','ilike','%'.$term.'%')
                ->orWhereHas('product', fn ($product) => $product->where('name','ilike','%'.$term.'%')->orWhere('sku','ilike','%'.$term.'%')));
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
            'pageStart','pageEnd','angleMobile','angleDesktop','angleIos'
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
                foreach (TryOnAsset::DEFAULTS as $key=>$default) $settings[$key] = $request->boolean($key);
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
        return redirect()->route('admin.tryons.index',['edit'=>$asset->id])->with('success','Virtual try-on asset saved.');
    }

    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, TryOnAsset $tryon) { return $this->save($request, $tryon); }

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
                $before = $asset->toArray();
                if ($data['action'] === 'delete') {
                    AuditTrail::record('tryon.deleted',$asset,$before,null);
                    $uuid = $asset->uuid;
                    $asset->delete();
                    DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory('tryons/'.$uuid));
                    continue;
                }
                if ($data['action'] === 'published' && !$asset->previewPath()) {
                    throw ValidationException::withMessages(['ids'=>'A selected asset has no browser preview overlay and cannot be published.']);
                }
                $asset->update(['status'=>$data['action'],'updated_by'=>auth()->user()->name]);
                AuditTrail::record('tryon.'.$data['action'],$asset,$before,$asset->fresh()->toArray());
            }
        });
        return back()->with('success','Selected virtual try-on assets updated.');
    }

    public function audit(TryOnAsset $tryon)
    {
        $entries = AuditLog::where('subject_type',TryOnAsset::class)
            ->where('subject_id',(string) $tryon->id)
            ->latest('created_at')->limit(50)->get(['action','created_at','user_id']);
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
