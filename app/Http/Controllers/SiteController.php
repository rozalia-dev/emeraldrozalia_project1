<?php
namespace App\Http\Controllers;
use App\Models\{Category,ContentPage,Conversation,FranchiseApplication,Inquiry,Product};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;
class SiteController extends Controller {
    public function home(){return view('site.home',['categories'=>Category::where('is_active',true)->orderBy('sort_order')->get(),'newProducts'=>Product::where('is_active',true)->where('is_new',true)->latest()->limit(8)->get()]);}
    public function collections(){return view('site.collections',['categories'=>Category::withCount(['products'=>fn($q)=>$q->where('is_active',true)])->where('is_active',true)->orderBy('sort_order')->get(),'bestsellers'=>Product::where('is_active',true)->latest()->limit(6)->get()]);}
    public function newArrivals(Request $r){
        $q=Product::with('category')->withCount('reviews')->withAvg('reviews','rating')->where('is_active',true)->where('is_new',true);
        if($r->filled('q'))$q->where(fn($x)=>$x->where('name','like','%'.$r->q.'%')->orWhere('sku','like','%'.$r->q.'%'));
        $categories=array_values(array_filter((array)$r->input('category',[]),fn($value)=>is_string($value)&&$value!==''));
        if($categories)$q->whereHas('category',fn($c)=>$c->whereIn('slug',$categories));
        $materials=array_values(array_filter((array)$r->input('material',[]),fn($value)=>is_string($value)&&$value!==''));
        if($materials)$q->where(function($materialQuery)use($materials){foreach($materials as $material)$materialQuery->orWhereRaw('LOWER(material) LIKE ?',['%'.strtolower($material).'%']);});
        $colours=array_values(array_filter((array)$r->input('colour',[]),fn($value)=>is_string($value)&&$value!==''));
        if($colours)$q->where(function($colourQuery)use($colours){foreach($colours as $colour)$colourQuery->orWhereRaw('LOWER(CAST(colours AS TEXT)) LIKE ?',['%"'.strtolower($colour).'"%']);});
        if($r->filled('max_price')&&is_numeric($r->input('max_price')))$q->where('price','<=',max(0,(float)$r->input('max_price')));
        match($r->input('sort')){'price_low'=>$q->orderBy('price'),'price_high'=>$q->orderByDesc('price'),'name'=>$q->orderBy('name'),default=>$q->latest()};
        return view('site.new-arrivals',['products'=>$q->paginate(12)->withQueryString(),'categories'=>Category::where('is_active',true)->orderBy('sort_order')->get()]);
    }
    public function virtualTryOn(Request $request){
        $products=Product::where('is_active',true)->with(['media','tryOnAssets'=>fn($query)=>$query->where('status','published')->where('visibility','public')->latest('updated_at')])->orderBy('name')->get();
        $selected=$products->firstWhere('id',(int)$request->input('product_id')) ?: $products->first();
        $assetMetaMap=[];
        $assetMap=$products->mapWithKeys(function($product)use(&$assetMetaMap){
            $assets=[];
            $managed=$product->tryOnAssets->first(fn($asset)=>$asset->isPublic());
            if($managed){
                $assets[]=$managed->previewUrl();
                $assetMetaMap[$product->id]=$managed->viewerData();
                return[$product->id=>$assets];
            }
            if(filled($product->try_on_asset)){
                if(str_starts_with($product->try_on_asset,'/')||str_starts_with($product->try_on_asset,'http'))$assets[]=$product->try_on_asset;
                elseif(Storage::disk('public')->exists($product->try_on_asset))$assets[]=Storage::disk('public')->url($product->try_on_asset);
            }
            foreach($product->media->where('type','try_on') as $media)$assets[]=Storage::disk($media->disk)->url($media->path);
            return[$product->id=>$assets];
        })->all();
        return view('site.virtual-tryon',compact('products','selected','assetMap','assetMetaMap'));
    }
    public function irishTraditional(Request $request){return $this->categoryLanding($request,'irish-traditional-flat-caps','IRISH TRADITIONAL','FLAT CAPS','Authentic Irish flat caps crafted from premium tweed. Timeless style. Made in Limerick, Ireland.');}
    public function irishHeritage(Request $request){return $this->categoryLanding($request,'irish-heritage-hats','IRISH HERITAGE','HATS','Classic hats with timeless Irish character. Crafted with care in Limerick using premium materials and traditional techniques.');}
    private function categoryLanding(Request $request,string $slug,string $eyebrow,string $title,string $intro){$category=Category::where('slug',$slug)->where('is_active',true)->first() ?: new Category(['name'=>trim($eyebrow.' '.$title)]);$query=$category->exists ? $category->products()->with('category')->where('is_active',true) : Product::whereRaw('1 = 0');if($request->filled('q'))$query->where(fn($q)=>$q->where('name','like','%'.$request->q.'%')->orWhere('sku','like','%'.$request->q.'%'));match($request->input('sort')){'price_low'=>$query->orderBy('price'),'price_high'=>$query->orderByDesc('price'),default=>$query->latest()};$products=$query->paginate(12)->withQueryString();return view('site.category-landing',compact('category','eyebrow','title','intro','products'));}
    public function factory(){return view('site.factory');}
    public function corporateOrders(){return view('site.corporate-order');}
    public function bulkOrders(){return view('site.bulk-order');}
    public function franchise(){return view('site.franchise');}
    public function careers(){return view('site.careers');}
    public function globalNetwork(){return view('site.global-network');}
    public function contact(){return view('site.contact');}
    public function shop(Request $r){$q=Product::with('category')->where('is_active',true);if($r->filled('q'))$q->where(fn($x)=>$x->where('name','like','%'.$r->q.'%')->orWhere('sku','like','%'.$r->q.'%'));if($r->filled('category'))$q->whereHas('category',fn($c)=>$c->where('slug',$r->category));return view('site.shop',['products'=>$q->latest()->paginate(12)->withQueryString(),'categories'=>Category::where('is_active',true)->get()]);}
    public function product(Product $product){abort_unless($product->is_active,404);$product->load(['variants','reviews.user','category','media']);$spinFrames=collect($product->spin_images??[])->merge($product->media->where('type','spin_360')->map(fn($media)=>Storage::disk($media->disk)->url($media->path)))->filter()->values()->all();$related=Product::where('is_active',true)->where('id','!=',$product->id)->when($product->category_id,fn($q)=>$q->where('category_id',$product->category_id))->limit(4)->get();return view('site.product',compact('product','related','spinFrames'));}
    public function category(Category $category){return view('site.shop',['products'=>$category->products()->where('is_active',true)->paginate(12),'categories'=>Category::where('is_active',true)->get(),'activeCategory'=>$category]);}
    public function page(string $page){$allowed=['collections','new-arrivals','corporate-orders','bulk-orders','franchise','careers','global-network','factory','contact','virtual-tryon','irish-traditional','irish-heritage'];$managedPage=ContentPage::with('sections')->where('slug',$page)->where('locale',app()->getLocale())->where('status','published')->where(fn($q)=>$q->whereNull('scheduled_for')->orWhere('scheduled_for','<=',now()))->first();abort_unless($managedPage || in_array($page,$allowed,true),404);return view('site.page',compact('page','managedPage'));}
    public function inquiry(Request $r){
        $meetingTimes=['09:00','10:00','11:00','14:00','15:00','16:00'];
        $requiresMessage=in_array($r->input('type'),['contact','franchise','corporate-orders','bulk-orders'],true);
        $requiresConsent=in_array($r->input('type'),['contact','franchise'],true);
        $d=$r->validate([
            'type'=>['required',Rule::in(['contact','franchise','careers','corporate-orders','bulk-orders'])],
            'name'=>'required|string|max:120','email'=>'required|email|max:255','phone'=>'nullable|string|max:50',
            'company'=>'nullable|string|max:120','country'=>'nullable|string|max:120','subject'=>'required_if:type,contact|nullable|string|max:150',
            'message'=>[Rule::requiredIf($requiresMessage),'nullable','string','max:5000'],
            'consent'=>$requiresConsent?['required','accepted']:['nullable'],
            'meeting_date'=>'nullable|required_with:meeting_time|date_format:Y-m-d|after_or_equal:today',
            'meeting_time'=>['nullable','required_with:meeting_date','date_format:H:i',Rule::in($meetingTimes)],
        ]);
        $meeting=array_filter(['date'=>$d['meeting_date']??null,'time'=>$d['meeting_time']??null],fn($value)=>filled($value));
        if($meeting){
            $slot=CarbonImmutable::createFromFormat('!Y-m-d H:i',$meeting['date'].' '.$meeting['time'],config('app.timezone'));
            if($slot->isWeekend())throw ValidationException::withMessages(['meeting_date'=>'Meetings are available Monday to Friday only.']);
            if($slot->lessThanOrEqualTo(now(config('app.timezone'))))throw ValidationException::withMessages(['meeting_time'=>'Please choose a future meeting time.']);
        }
        $country=$d['country']??null;
        unset($d['meeting_date'],$d['meeting_time'],$d['consent'],$d['country']);
        $d['meta']=['source'=>'public_'.$d['type'].'_form','meeting'=>$meeting?:null,'country'=>$country];
        DB::transaction(function()use($d,$meeting){
            Inquiry::create($d);
            if($d['type']==='franchise')FranchiseApplication::create(['applicant_name'=>$d['name'],'email'=>$d['email'],'phone'=>$d['phone']??null,'territory'=>'Ireland','preferred_location'=>$d['company']??null,'business_experience'=>$d['message']??null,'status'=>'new','data'=>['source'=>'public_franchise_form']]);
            $conversation=Conversation::create(['channel'=>'web','contact'=>$d['email'],'subject'=>$d['subject']??str($d['type'])->headline(),'priority'=>$d['type']==='franchise'?'high':'normal','status'=>'new','metadata'=>['type'=>$d['type'],'name'=>$d['name'],'phone'=>$d['phone']??null,'company'=>$d['company']??null,'country'=>$d['meta']['country']??null,'meeting'=>$meeting?:null]]);
            $body=$d['message']??'Public form submission';
            if($meeting)$body.="\n\nMeeting requested: {$meeting['date']} at {$meeting['time']} (Europe/Dublin).";
            $conversation->messages()->create(['direction'=>'inbound','body'=>$body,'delivery_status'=>'stored','sent_at'=>now()]);
        });
        return back()->with('success','Thank you. Your enquiry is now in our Communication Centre.');
    }
}
