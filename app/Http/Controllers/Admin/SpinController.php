<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product,ProductSpin,SpinVisit,AuditLog};
use App\Services\{AuditTrail,SpinFrames};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\Rule;

class SpinController extends Controller
{
    private function filtered(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:150','product_id'=>'nullable|integer','status'=>['nullable',Rule::in(array_keys(ProductSpin::STATUSES))],'category'=>['nullable',Rule::in(array_keys(ProductSpin::CATEGORIES))],'device'=>'nullable|in:mobile,desktop']);
        $q=ProductSpin::with('product');
        if ($term=trim((string)$request->input('q'))) $q->where(fn($q)=>$q->where('title','ilike','%'.$term.'%')->orWhereHas('product',fn($p)=>$p->where('name','ilike','%'.$term.'%')->orWhere('sku','ilike','%'.$term.'%')));
        foreach (['product_id','status','category'] as $key) if ($request->filled($key)) $q->where($key,$request->input($key));
        if ($request->input('device')==='mobile') $q->where('settings->mobile',true);
        if ($request->input('device')==='desktop') $q->where('settings->mobile',false);
        return $q;
    }
    public function index(Request $request)
    {
        $perPage=in_array($request->integer('per_page'),[8,20,40],true)?$request->integer('per_page'):8;
        $spins=$this->filtered($request)->withCount(['visits'=>fn($q)=>$q->where('day','>=',now()->subDays(29)->toDateString())])->latest()->paginate($perPage)->withQueryString();
        $all=ProductSpin::get();
        $visits=SpinVisit::whereIn('product_spin_id',$all->modelKeys())->where('day','>=',now()->subDays(29)->toDateString());
        $stats=['total'=>$all->count(),'bytes'=>$all->sum('bytes'),'views'=>(clone $visits)->count(),'unique'=>(clone $visits)->distinct()->count('visitor_hash'),'engaged'=>(clone $visits)->where('engaged',true)->count(),'load_ms'=>(clone $visits)->where('load_ms','>',0)->avg('load_ms')];
        foreach(ProductSpin::STATUSES as $key=>$label) $stats[$key]=$all->where('status',$key)->count();
        $types=$all->countBy('category');
        $products=Product::orderBy('name')->get(['id','name','sku']);
        $selected=$request->filled('edit')?ProductSpin::findOrFail($request->integer('edit')):$spins->first();
        return view('admin.spins.index',compact('spins','stats','types','products','selected'));
    }
    private function save(Request $request, ?ProductSpin $spin=null)
    {
        $data=$request->validate([
            'product_id'=>'required|integer','title'=>'required|string|max:160',
            'category'=>['required',Rule::in(array_keys(ProductSpin::CATEGORIES))],
            'status'=>['required',Rule::in(array_keys(ProductSpin::STATUSES))],
            'visibility'=>'required|in:public,private',
            'archive'=>[$spin?'nullable':'required','file','max:20480','mimes:zip'],
            'alt'=>'nullable|string|max:500','seo_title'=>'nullable|string|max:160','aria'=>'nullable|string|max:200',
            'hotspot_data'=>'nullable|json|max:12000',
            'auto_rotate'=>'nullable|boolean','zoom'=>'nullable|boolean','fullscreen'=>'nullable|boolean','hotspots'=>'nullable|boolean','lazy_load'=>'nullable|boolean','mobile'=>'nullable|boolean',
        ]);
        Product::findOrFail($data['product_id']);
        $hotspots=json_decode($data['hotspot_data']??'[]',true);
        validator(['spots'=>$hotspots],['spots'=>'array|max:20','spots.*'=>'array:frame,x,y,label','spots.*.frame'=>'required|integer|min:0|max:71','spots.*.x'=>'required|numeric|between:0,100','spots.*.y'=>'required|numeric|between:0,100','spots.*.label'=>'required|string|max:180'])->validate();
        $stored=$request->hasFile('archive')?app(SpinFrames::class)->store($request->file('archive')):null;
        try {
            DB::transaction(function() use($request,$data,$hotspots,$stored,&$spin) {
                $exists=$spin!==null;
                $spin=$exists?ProductSpin::whereKey($spin->id)->lockForUpdate()->firstOrFail():new ProductSpin();
                $before=$exists?$spin->toArray():null;
                $old=$spin->frames??[];
                $frames=$stored['frames']??$old;
                validator(['frames'=>$frames],['frames'=>'array|min:2|max:72'])->validate();
                foreach($hotspots as $spot) abort_if($spot['frame']>=count($frames),422,'Hotspot frame is outside this frame set.');
                $settings=[];
                foreach(ProductSpin::DEFAULTS as $key=>$value) $settings[$key]=$request->boolean($key);
                $spin->fill(array_intersect_key($data,array_flip(['product_id','title','category','status','visibility'])));
                $spin->fill(['frames'=>$frames,'settings'=>$settings,'seo'=>['alt'=>$data['alt']??$data['title'],'title'=>$data['seo_title']??$data['title'],'aria'=>$data['aria']??$data['title']],
                    'hotspots'=>$hotspots,'updated_by'=>$request->user()->name,'bytes'=>$stored['bytes']??$spin->bytes??0,'resolution'=>$stored['resolution']??$spin->resolution]);
                $spin->save();
                AuditTrail::record($exists?'spin.updated':'spin.created',$spin,$before,$spin->toArray());
                if($stored && $old) DB::afterCommit(fn()=>Storage::disk('local')->delete($old));
            });
        } catch(\Throwable $e) {
            if($stored) Storage::disk('local')->deleteDirectory($stored['directory']);
            throw $e;
        }
        return redirect()->route('admin.spins.index',['edit'=>$spin->id])->with('success','360° view saved.');
    }
    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, ProductSpin $spin) { return $this->save($request,$spin); }
    public function bulk(Request $request)
    {
        $data=$request->validate(['ids'=>'required|array|min:1|max:100','ids.*'=>'integer|distinct','action'=>['required',Rule::in(['published','draft','archived','delete'])]]);
        DB::transaction(function()use($data){
            $spins=ProductSpin::whereIn('id',$data['ids'])->lockForUpdate()->get();
            abort_unless($spins->count()===count($data['ids']),422,'Select views in the current company.');
            foreach($spins as $spin){
                $before=$spin->toArray();
                if($data['action']==='delete'){
                    AuditTrail::record('spin.deleted',$spin,$before,null); $files=$spin->frames; $spin->delete();
                    DB::afterCommit(fn()=>Storage::disk('local')->delete($files));
                }else{
                    $spin->update(['status'=>$data['action'],'updated_by'=>auth()->user()->name]);
                    AuditTrail::record('spin.'.$data['action'],$spin,$before,$spin->toArray());
                }
            }
        });
        return back()->with('success','Selected 360° views updated.');
    }
    public function audit(ProductSpin $spin)
    {
        $entries=AuditLog::where('subject_type',ProductSpin::class)->where('subject_id',(string)$spin->id)->latest('created_at')->limit(50)->get(['action','created_at','user_id']);
        return response()->json(['uuid'=>$spin->uuid,'entries'=>$entries]);
    }
    public function export(Request $request)
    {
        $query=$this->filtered($request);
        return response()->streamDownload(function()use($query){
            $out=fopen('php://output','w'); fputcsv($out,['UUID','Product','SKU','Title','Type','Status','Visibility','Frames','Bytes'],',','"','');
            foreach($query->orderBy('id')->cursor() as $spin){
                $row=[$spin->uuid,$spin->product?->name,$spin->product?->sku,$spin->title,$spin->category,$spin->status,$spin->visibility,count($spin->frames),$spin->bytes];
                fputcsv($out,array_map(fn($v)=>preg_match('/^[=+\-@\t\r]/',(string)$v)?"'".$v:$v,$row),',','"','');
            } fclose($out);
        },'360-views.csv',['Content-Type'=>'text/csv']);
    }
}
