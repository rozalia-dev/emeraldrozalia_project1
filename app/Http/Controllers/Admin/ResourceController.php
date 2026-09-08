<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Conversation;
use App\Services\AuditTrail;
use Illuminate\Http\Request;
class ResourceController extends Controller {
    public array $modules=[
        'website-products','products','product-manager','add-product','online-sales','customers','cart-checkout','payments','reconciliation','payment-gateways',
        'franchise-management','communication-center','reports','users-roles','integrations','settings','audit-logs','automation',
        'backup-recovery','system-maintenance','returns-refunds','media-manager','images','videos','360-product-view','virtual-try-on',
        'categories','collections','variants','banners-sliders','seo-content','reviews-testimonials','reviews-ratings','shipping-delivery',
        'discounts-coupons','sales-reports','franchise-dashboard','franchise-applications','franchise-territories','franchise-agreements',
        'franchisees','franchise-retail-stores','training-documents','marketing-assets','performance-targets','renewals','inbox','chat-24-7',
        'whatsapp','email','email-templates','approval-center','action-follow-ups','alerts-notifications','communication-reports','communication-history',
        'customer-order-reports','users','roles','permissions','company-profile','language','currency','payment-settings','notifications','branding','security',
    ];
    private function valid(string $module):void { abort_unless(in_array($module,$this->modules,true),404); }
    private function data(Request $r):array { return $r->validate(['title'=>'required|max:180','reference'=>'nullable|max:100','status'=>'required|max:50','amount'=>'nullable|numeric','record_date'=>'nullable|date','notes'=>'nullable|max:3000']); }
    public function index(Request $request,string $module){$this->valid($module);if($module==='product-manager')return $this->productManager($request);if(in_array($module,['communication-center','inbox'],true))return $this->communicationCenter($request,$module);if($module==='reviews-ratings')return $this->reviewsRatings($request);$records=AdminRecord::where('module',$module)->latest()->paginate(25);return view('admin.resources.index',compact('module','records'));}
    private function reviewsRatings(Request $request){
        $reviews = collect([
            (object)['id'=>1,'title'=>'Excellent quality!','comment'=>'The cap quality is outstanding. Very comfortable and stylish.','product_name'=>'Emerald Signature Cap','sku'=>'ER-CAP-001','rating'=>5.0,'customer'=>'Michael O\'Connor','verified'=>true,'source'=>'Website','source_icon'=>'globe','status'=>'Approved','date'=>'01 May 2025','time'=>'10:30 AM','image'=>'assets/products/cap.jpg'],
            (object)['id'=>2,'title'=>'Great fit and design','comment'=>'Love the premium feel and the adjustable strap.','product_name'=>'Luxury Baseball Cap','sku'=>'ER-CAP-013','rating'=>4.0,'customer'=>'Sarah Kelly','verified'=>true,'source'=>'Website','source_icon'=>'globe','status'=>'Approved','date'=>'01 May 2025','time'=>'09:15 AM','image'=>'assets/products/cap2.jpg'],
            (object)['id'=>3,'title'=>'Very happy with purchase','comment'=>'Perfect for everyday wear. Will buy again!','product_name'=>'Premium Bucket Hat','sku'=>'ER-HAT-021','rating'=>5.0,'customer'=>'James Byrne','verified'=>true,'source'=>'WhatsApp','source_icon'=>'message-circle','status'=>'Approved','date'=>'30 Apr 2025','time'=>'08:20 PM','image'=>'assets/products/hat.jpg'],
            (object)['id'=>4,'title'=>'Colour slightly different','comment'=>'The color is a bit lighter than shown in the pictures.','product_name'=>'Classic Snapback Cap','sku'=>'ER-CAP-007','rating'=>3.0,'customer'=>'Aoife Walsh','verified'=>false,'source'=>'Website','source_icon'=>'globe','status'=>'Pending','date'=>'30 Apr 2025','time'=>'05:45 PM','image'=>'assets/products/cap3.jpg'],
            (object)['id'=>5,'title'=>'Amazing product!','comment'=>'Top notch quality and fast delivery.','product_name'=>'Emerald Trucker Cap','sku'=>'ER-CAP-009','rating'=>5.0,'customer'=>'Liam Murphy','verified'=>false,'source'=>'Google','source_icon'=>'search','status'=>'Approved','date'=>'30 Apr 2025','time'=>'02:10 PM','image'=>'assets/products/cap4.jpg'],
            (object)['id'=>6,'title'=>'Not as expected','comment'=>'The material feels cheap for the price.','product_name'=>'Urban Street Cap','sku'=>'ER-CAP-015','rating'=>2.0,'customer'=>'Declan Brown','verified'=>true,'source'=>'Website','source_icon'=>'globe','status'=>'Flagged','date'=>'29 Apr 2025','time'=>'11:05 AM','image'=>'assets/products/cap5.jpg'],
            (object)['id'=>7,'title'=>'Worth every penny','comment'=>'Excellent craftsmanship and premium packaging.','product_name'=>'Signature Wool Hat','sku'=>'ER-HAT-008','rating'=>5.0,'customer'=>'Niamh O\'Reilly','verified'=>false,'source'=>'Email','source_icon'=>'mail','status'=>'Approved','date'=>'29 Apr 2025','time'=>'10:20 AM','image'=>'assets/products/hat2.jpg'],
            (object)['id'=>8,'title'=>'Good but delivery was slow','comment'=>'Product is good, but took longer than expected to arrive.','product_name'=>'Flex Fit Cap','sku'=>'ER-CAP-003','rating'=>4.0,'customer'=>'Conor Gallagher','verified'=>true,'source'=>'Website','source_icon'=>'globe','status'=>'Approved','date'=>'28 Apr 2025','time'=>'04:30 PM','image'=>'assets/products/cap6.jpg'],
        ]);
        return view('admin.reviews-ratings.index', compact('reviews'));
    }
    private function communicationCenter(Request $request,string $module){$status=(string)$request->query('status','');$query=Conversation::with(['messages'=>fn($messages)=>$messages->oldest(),'assignee'])->latest();if(in_array($status,['new','open','pending','closed'],true))$query->where('status',$status);$conversations=$query->paginate(25)->withQueryString();return view('admin.communication-center.index',compact('module','conversations','status'));}
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
