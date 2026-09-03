<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Services\AuditTrail;
use Illuminate\Http\Request;
class ResourceController extends Controller {
    public array $modules=[
        'website-products','products','product-manager','add-product','online-sales','customers','cart-checkout','payments',
        'franchise-management','communication-center','reports','users-roles','integrations','settings','audit-logs','automation',
        'backup-recovery','system-maintenance','returns-refunds','media-manager','images','videos','360-product-view','virtual-try-on',
        'categories','collections','variants','banners-sliders','seo-content','reviews-testimonials','reviews-ratings','shipping-delivery',
        'discounts-coupons','sales-reports','franchise-dashboard','franchise-applications','franchise-territories','franchise-agreements',
        'franchisees','franchise-retail-stores','training-documents','marketing-assets','performance-targets','renewals','inbox','chat-24-7',
        'whatsapp','email','email-templates','approval-center','action-follow-ups','alerts-notifications','communication-reports','communication-history',
    ];
    private function valid(string $module):void { abort_unless(in_array($module,$this->modules,true),404); }
    private function data(Request $r):array { return $r->validate(['title'=>'required|max:180','reference'=>'nullable|max:100','status'=>'required|max:50','amount'=>'nullable|numeric','record_date'=>'nullable|date','notes'=>'nullable|max:3000']); }
    public function index(Request $request,string $module){$this->valid($module);if($module==='product-manager')return $this->productManager($request);$records=AdminRecord::where('module',$module)->latest()->paginate(25);return view('admin.resources.index',compact('module','records'));}
    private function productManager(Request $request){
        $tabs=['all'=>'All Products','published'=>'Published','draft'=>'Draft','hidden'=>'Hidden','out_of_stock'=>'Out of Stock','low_stock'=>'Low Stock','featured'=>'Featured','top_rated'=>'Top Rated'];
        $tab=(string)$request->query('tab','all');if(!array_key_exists($tab,$tabs))$tab='all';
        $query=Product::query()->with('category')->withCount('reviews')->withAvg('reviews','rating');
        switch($tab){
            case 'published':$query->where('is_active',true)->whereIn('status',['active','published']);break;
            case 'draft':$query->whereIn('status',['draft','planned']);break;
            case 'hidden':$query->where('is_active',false)->whereNotIn('status',['draft','planned']);break;
            case 'out_of_stock':$query->where('stock','<=',0);break;
            case 'low_stock':$query->whereBetween('stock',[1,10]);break;
            case 'featured':$query->where('is_new',true);break;
            case 'top_rated':$query->whereHas('reviews',fn($reviews)=>$reviews->where('rating','>=',4));break;
        }
        $search=trim((string)$request->query('q',''));if($search!=='')$query->where(fn($products)=>$products->where('name','like','%'.$search.'%')->orWhere('sku','like','%'.$search.'%')->orWhere('brand','like','%'.$search.'%'));
        $categoryId=(int)$request->query('category_id',0);if($categoryId>0)$query->where('category_id',$categoryId);
        $minPrice=$request->query('min_price');if(is_numeric($minPrice))$query->where('price','>=',(float)$minPrice);
        $maxPrice=$request->query('max_price');if(is_numeric($maxPrice))$query->where('price','<=',(float)$maxPrice);
        switch((string)$request->query('stock_status','')){case 'in_stock':$query->where('stock','>',0);break;case 'low_stock':$query->whereBetween('stock',[1,10]);break;case 'out_of_stock':$query->where('stock','<=',0);break;}
        switch((string)$request->query('product_status','')){case 'published':$query->where('is_active',true)->whereIn('status',['active','published']);break;case 'draft':$query->whereIn('status',['draft','planned']);break;case 'hidden':$query->where('is_active',false);break;case 'inactive':$query->where('is_active',false)->where('status','inactive');break;}
        $rating=(string)$request->query('rating','');if(preg_match('/^[1-5]_plus$/',$rating))$query->whereHas('reviews',fn($reviews)=>$reviews->where('rating','>=',(int)$rating[0]));
        if($request->boolean('featured'))$query->where('is_new',true);
        $products=$query->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();
        $totalProducts=(int)Product::query()->count();$draftCount=(int)Product::query()->whereIn('status',['draft','planned'])->count();$hiddenCount=(int)Product::query()->where('is_active',false)->whereNotIn('status',['draft','planned'])->count();
        $stats=[
            'total'=>$totalProducts,
            'published'=>(int)Product::query()->where('is_active',true)->whereIn('status',['active','published'])->count(),
            'hidden_draft'=>$draftCount+$hiddenCount,
            'draft'=>$draftCount,
            'hidden'=>$hiddenCount,
            'out_of_stock'=>(int)Product::query()->where('stock','<=',0)->count(),
            'total_value'=>(float)(Product::query()->selectRaw('COALESCE(SUM(price * stock), 0) AS aggregate')->value('aggregate')??0),
            'average_rating'=>(float)(Review::query()->where('status','approved')->whereHas('product')->avg('rating')??0),
        ];
        $categories=Category::query()->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(['id','name']);
        return view('admin.product-manager.index',compact('products','categories','stats','tabs','tab','search','categoryId','minPrice','maxPrice','rating'));
    }
    public function store(Request $r,string $module){$this->valid($module);$d=$this->data($r);$record=AdminRecord::create(['module'=>$module,'title'=>$d['title'],'reference'=>$d['reference']??null,'status'=>$d['status'],'amount'=>$d['amount']??null,'record_date'=>$d['record_date']??null,'user_id'=>auth()->id(),'data'=>['notes'=>$d['notes']??null]]);AuditTrail::record($module.'.created',$record,null,$record->toArray());return back()->with('success','Record created.');}
    public function update(Request $r,string $module,AdminRecord $record){$this->valid($module);abort_unless($record->module===$module,404);$before=$record->toArray();$d=$this->data($r);$record->update(['title'=>$d['title'],'reference'=>$d['reference']??null,'status'=>$d['status'],'amount'=>$d['amount']??null,'record_date'=>$d['record_date']??null,'data'=>['notes'=>$d['notes']??null]]);AuditTrail::record($module.'.updated',$record,$before,$record->fresh()->toArray());return back()->with('success','Record updated.');}
    public function destroy(string $module,AdminRecord $record){$this->valid($module);abort_unless($record->module===$module,404);$before=$record->toArray();AuditTrail::record($module.'.deleted',$record,$before,null);$record->delete();return back()->with('success','Record deleted.');}
}
