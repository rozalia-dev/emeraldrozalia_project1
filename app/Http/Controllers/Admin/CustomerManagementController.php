<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerManagementController extends Controller
{
    private const GROUP_TYPES = ['retail','corporate','franchise','franchise_retail','bulk','buyer','vip','system'];
    private const SEGMENT_TYPES = ['behavior','value','demographic','geographic','lifecycle','rfm','custom'];
    private const ACCOUNT_STATUSES = ['active','inactive','blocked','restricted'];

    public function index(Request $request)
    {
        $query = User::query()->where('is_admin', false)
            ->with(['customerProfile.primaryGroup','customerGroups','customerSegments'])
            ->withCount('orders')->withSum('orders','total')->withMax('orders','created_at');
        $this->applyCustomerFilters($query,$request);
        $customers = $query->orderBy('name')->paginate($this->perPage($request))->withQueryString();
        $selected = $this->selectedCustomer($request,$customers->items());

        $stats = [
            'total'=>User::where('is_admin',false)->count(),
            'active'=>User::where('is_admin',false)->where(fn(Builder $q)=>$q->whereDoesntHave('customerProfile')->orWhereHas('customerProfile',fn($p)=>$p->where('account_status','active')))->count(),
            'new'=>User::where('is_admin',false)->where('created_at','>=',now()->subDays(30))->count(),
            'vip'=>User::where('is_admin',false)->whereHas('customerProfile',fn($q)=>$q->where('is_vip',true))->count(),
            'orders'=>Order::whereNotNull('user_id')->count(),
            'spent'=>(float)Order::whereNotNull('user_id')->sum('total'),
        ];
        $stats['aov']=$stats['orders']?$stats['spent']/$stats['orders']:0;

        $groups=CustomerGroup::where('is_active',true)->orderBy('name')->get();
        $segments=CustomerSegment::where('is_active',true)->orderBy('name')->get();
        $countries=\App\Models\CustomerProfile::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');
        $profileData=$this->customerProfileData($selected);
        return view('admin.customers.index',compact('customers','selected','stats','groups','segments','countries','profileData'));
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'name'=>['required','string','max:150'],'email'=>['required','email','max:190','unique:users,email'],'phone'=>['nullable','string','max:40'],
            'account_status'=>['required',Rule::in(self::ACCOUNT_STATUSES)],'country'=>['nullable','string','size:2'],'preferred_language'=>['nullable','string','max:10'],
            'group_id'=>['nullable','integer','exists:customer_groups,id'],'segment_ids'=>['nullable','array'],'segment_ids.*'=>['integer','exists:customer_segments,id'],
            'is_vip'=>['nullable','boolean'],'marketing_consent'=>['nullable','boolean'],'notes'=>['nullable','string','max:3000'],
        ]);
        return DB::transaction(function()use($data){
            $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'phone'=>$data['phone']??null,'password'=>Hash::make(Str::random(40)),'is_admin'=>false]);
            $profile=$user->customerProfile()->create(['uuid'=>(string)Str::uuid(),'primary_group_id'=>$data['group_id']??null,'account_status'=>$data['account_status'],'country'=>isset($data['country'])?strtoupper($data['country']):null,'preferred_language'=>$data['preferred_language']??'en','is_vip'=>(bool)($data['is_vip']??false),'marketing_consent'=>(bool)($data['marketing_consent']??false),'consent_at'=>!empty($data['marketing_consent'])?now():null,'notes'=>$data['notes']??null]);
            if(!empty($data['group_id']))$user->customerGroups()->syncWithoutDetaching([$data['group_id']]);
            $user->customerSegments()->sync($data['segment_ids']??[]);
            AuditTrail::record('customer.created',$user,null,['profile'=>$profile->toArray()]);
            return redirect()->route('admin.customers.index',['selected'=>$user->id])->with('success','Customer created.');
        });
    }

    public function update(Request $request,User $customer)
    {
        abort_if($customer->is_admin,404);
        $data=$request->validate([
            'name'=>['required','string','max:150'],'email'=>['required','email','max:190',Rule::unique('users','email')->ignore($customer->id)],'phone'=>['nullable','string','max:40'],
            'account_status'=>['required',Rule::in(self::ACCOUNT_STATUSES)],'country'=>['nullable','string','size:2'],'preferred_language'=>['nullable','string','max:10'],
            'group_id'=>['nullable','integer','exists:customer_groups,id'],'segment_ids'=>['nullable','array'],'segment_ids.*'=>['integer','exists:customer_segments,id'],
            'is_vip'=>['nullable','boolean'],'marketing_consent'=>['nullable','boolean'],'notes'=>['nullable','string','max:3000'],
        ]);
        $before=$customer->load('customerProfile')->toArray();
        DB::transaction(function()use($customer,$data){
            $customer->update(['name'=>$data['name'],'email'=>$data['email'],'phone'=>$data['phone']??null]);
            $profile=$customer->customerProfile()->firstOrCreate([],['uuid'=>(string)Str::uuid()]);
            $profile->update(['primary_group_id'=>$data['group_id']??null,'account_status'=>$data['account_status'],'country'=>isset($data['country'])?strtoupper($data['country']):null,'preferred_language'=>$data['preferred_language']??'en','is_vip'=>(bool)($data['is_vip']??false),'marketing_consent'=>(bool)($data['marketing_consent']??false),'consent_at'=>!empty($data['marketing_consent'])?($profile->consent_at?:now()):null,'notes'=>$data['notes']??null]);
            $customer->customerGroups()->sync(!empty($data['group_id'])?[$data['group_id']]:[]);
            $customer->customerSegments()->sync($data['segment_ids']??[]);
        });
        AuditTrail::record('customer.updated',$customer,$before,$customer->fresh()->load('customerProfile')->toArray());
        return back()->with('success','Customer updated.');
    }

    public function destroy(User $customer)
    {
        abort_if($customer->is_admin,404);$before=$customer->toArray();AuditTrail::record('customer.deleted',$customer,$before,null);$customer->delete();
        return redirect()->route('admin.customers.index')->with('success','Customer deleted.');
    }

    public function exportCustomers(Request $request):StreamedResponse
    {
        $query=User::query()->where('is_admin',false)->with(['customerProfile.primaryGroup','customerSegments']);$this->applyCustomerFilters($query,$request);
        return response()->streamDownload(function()use($query){$out=fopen('php://output','w');fputcsv($out,['name','email','phone','status','country','group','segments','uuid']);$query->orderBy('id')->chunk(250,function($users)use($out){foreach($users as $u)fputcsv($out,[$u->name,$u->email,$u->phone,$u->customerProfile?->account_status??'active',$u->customerProfile?->country,$u->customerProfile?->primaryGroup?->name,$u->customerSegments->pluck('name')->implode('|'),$u->customerProfile?->uuid??$u->public_uuid]);});fclose($out);},'customers.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function importCustomers(Request $request)
    {
        $file=$this->csv($request);$count=0;$handle=fopen($file->getRealPath(),'r');$header=array_map('trim',fgetcsv($handle)?:[]);
        while(($row=fgetcsv($handle))!==false){if(count($row)!==count($header))continue;$d=array_combine($header,$row);if(empty($d['email'])||empty($d['name']))continue;$user=User::firstOrCreate(['email'=>$d['email']],['name'=>$d['name'],'phone'=>$d['phone']??null,'password'=>Hash::make(Str::random(40)),'is_admin'=>false]);if($user->is_admin)continue;$user->update(['name'=>$d['name'],'phone'=>$d['phone']??$user->phone]);$group=!empty($d['group'])?CustomerGroup::where('name',$d['group'])->first():null;$profile=$user->customerProfile()->firstOrCreate([],['uuid'=>(string)Str::uuid()]);$status=in_array($d['status']??'active',self::ACCOUNT_STATUSES,true)?$d['status']:'active';$profile->update(['account_status'=>$status,'country'=>!empty($d['country'])?strtoupper($d['country']):null,'primary_group_id'=>$group?->id]);if($group)$user->customerGroups()->syncWithoutDetaching([$group->id]);$count++;}
        fclose($handle);AuditTrail::record('customers.imported',null,null,['count'=>$count]);return back()->with('success',$count.' customers imported.');
    }

    public function groups(Request $request)
    {
        $query=CustomerGroup::query()->withCount('customers')->with('creator');$q=trim((string)$request->query('q',''));
        if($q!=='')$query->where(fn($x)=>$x->where('name','like','%'.$q.'%')->orWhere('description','like','%'.$q.'%')->orWhereRaw('CAST(uuid AS TEXT) LIKE ?', ['%'.$q.'%']));
        if($request->filled('type'))$query->where('type',(string)$request->query('type'));if($request->filled('status'))$query->where('is_active',$request->query('status')==='active');if($request->filled('country'))$query->where('country',(string)$request->query('country'));
        if($request->filled('tab')&&$request->query('tab')!=='all'){$tab=(string)$request->query('tab');$tab==='inactive'?$query->where('is_active',false):$query->where('type',$tab);}
        $groups=$query->orderBy('name')->paginate($this->perPage($request))->withQueryString();$selected=$this->selectedModel(CustomerGroup::class,$request,$groups->items());
        $stats=['total'=>CustomerGroup::count(),'customers'=>(int)DB::table('customer_group_user')->distinct()->count('user_id'),'active'=>CustomerGroup::where('is_active',true)->count(),'new'=>CustomerGroup::where('created_at','>=',now()->subDays(30))->count(),'pricing'=>CustomerGroup::whereNotNull('pricing_rule')->count(),'vip'=>CustomerGroup::where('is_vip',true)->count()];
        $groupData=$this->groupData($selected);$countries=CustomerGroup::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');$typeDistribution=CustomerGroup::selectRaw('type, COUNT(*) as aggregate')->groupBy('type')->orderByDesc('aggregate')->get();$topGroups=CustomerGroup::withCount('customers')->orderByDesc('customers_count')->limit(5)->get();$availableCustomers=User::where('is_admin',false)->orderBy('name')->limit(500)->get(['id','name','email']);
        if($selected)$selected->loadMissing('customers');
        return view('admin.customers.groups',compact('groups','selected','stats','groupData','countries','typeDistribution','topGroups','availableCustomers'));
    }

    public function storeGroup(Request $request)
    {
        $data=$this->groupPayload($request);$group=CustomerGroup::create([...$data,'uuid'=>(string)Str::uuid(),'slug'=>$this->uniqueSlug(CustomerGroup::class,$data['name']),'created_by'=>auth()->id()]);if($request->has('customer_ids'))$group->customers()->sync($request->input('customer_ids',[]));AuditTrail::record('customer-group.created',$group,null,$group->toArray());return redirect()->route('admin.customer-groups.index',['selected'=>$group->id])->with('success','Customer group created.');
    }
    public function updateGroup(Request $request,CustomerGroup $group){$before=$group->toArray();$group->update($this->groupPayload($request));if($request->has('customer_ids'))$group->customers()->sync($request->input('customer_ids',[]));AuditTrail::record('customer-group.updated',$group,$before,$group->fresh()->toArray());return back()->with('success','Customer group updated.');}
    public function duplicateGroup(CustomerGroup $group){$copy=$group->replicate(['uuid','slug']);$copy->uuid=(string)Str::uuid();$copy->name=$group->name.' Copy';$copy->slug=$this->uniqueSlug(CustomerGroup::class,$copy->name);$copy->created_by=auth()->id();$copy->save();$copy->customers()->sync($group->customers()->pluck('users.id')->all());AuditTrail::record('customer-group.duplicated',$copy,null,$copy->toArray());return redirect()->route('admin.customer-groups.index',['selected'=>$copy->id])->with('success','Group duplicated.');}
    public function destroyGroup(CustomerGroup $group){AuditTrail::record('customer-group.deleted',$group,$group->toArray(),null);$group->delete();return redirect()->route('admin.customer-groups.index')->with('success','Customer group deleted.');}

    public function exportGroups():StreamedResponse
    {
        return response()->streamDownload(function(){$out=fopen('php://output','w');fputcsv($out,['name','type','description','pricing_rule','discount_note','country','status','vip','uuid']);CustomerGroup::orderBy('id')->chunk(250,function($groups)use($out){foreach($groups as $g)fputcsv($out,[$g->name,$g->type,$g->description,$g->pricing_rule,$g->discount_note,$g->country,$g->is_active?'active':'inactive',$g->is_vip?'1':'0',$g->uuid]);});fclose($out);},'customer-groups.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function importGroups(Request $request)
    {
        $file=$this->csv($request);$count=0;$h=fopen($file->getRealPath(),'r');$header=array_map('trim',fgetcsv($h)?:[]);while(($row=fgetcsv($h))!==false){if(count($row)!==count($header))continue;$d=array_combine($header,$row);if(empty($d['name']))continue;$payload=['type'=>in_array($d['type']??'retail',self::GROUP_TYPES,true)?$d['type']:'retail','description'=>$d['description']??null,'pricing_rule'=>$d['pricing_rule']??null,'discount_note'=>$d['discount_note']??null,'country'=>!empty($d['country'])?strtoupper($d['country']):null,'is_active'=>($d['status']??'active')!=='inactive','is_vip'=>filter_var($d['vip']??false,FILTER_VALIDATE_BOOLEAN)];$group=CustomerGroup::where('name',$d['name'])->first();if($group)$group->update($payload);else CustomerGroup::create([...$payload,'name'=>$d['name'],'uuid'=>!empty($d['uuid'])?$d['uuid']:(string)Str::uuid(),'slug'=>$this->uniqueSlug(CustomerGroup::class,$d['name']),'created_by'=>auth()->id()]);$count++;}fclose($h);AuditTrail::record('customer-groups.imported',null,null,['count'=>$count]);return back()->with('success',$count.' groups imported.');
    }

    public function segments(Request $request)
    {
        $query=CustomerSegment::query()->withCount('customers')->with('creator');$q=trim((string)$request->query('q',''));
        if($q!=='')$query->where(fn($x)=>$x->where('name','like','%'.$q.'%')->orWhere('description','like','%'.$q.'%')->orWhereRaw('CAST(uuid AS TEXT) LIKE ?', ['%'.$q.'%']));
        if($request->filled('type'))$query->where('type',(string)$request->query('type'));if($request->filled('status'))$query->where('is_active',$request->query('status')==='active');if($request->filled('country'))$query->where('country',(string)$request->query('country'));
        if($request->filled('tab')&&$request->query('tab')!=='all'){$tab=(string)$request->query('tab');$tab==='inactive'?$query->where('is_active',false):$query->where('type',$tab);}
        $segments=$query->orderBy('name')->paginate($this->perPage($request))->withQueryString();$selected=$this->selectedModel(CustomerSegment::class,$request,$segments->items());$totalCustomers=User::where('is_admin',false)->count();$membership=(int)DB::table('customer_segment_user')->distinct()->count('user_id');$stats=['total'=>CustomerSegment::count(),'customers'=>$membership,'active'=>CustomerSegment::where('is_active',true)->count(),'new'=>CustomerSegment::where('created_at','>=',now()->subDays(30))->count(),'coverage'=>$totalCustomers?($membership/$totalCustomers)*100:0];
        $segmentData=$this->segmentData($selected,$totalCustomers);$countries=CustomerSegment::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');$typeDistribution=CustomerSegment::selectRaw('type, COUNT(*) as aggregate')->groupBy('type')->orderByDesc('aggregate')->get();$topSegments=CustomerSegment::withCount('customers')->orderByDesc('customers_count')->limit(5)->get();$availableCustomers=User::where('is_admin',false)->orderBy('name')->limit(500)->get(['id','name','email']);if($selected)$selected->loadMissing('customers');
        return view('admin.customers.segments',compact('segments','selected','stats','segmentData','countries','typeDistribution','topSegments','totalCustomers','availableCustomers'));
    }

    public function storeSegment(Request $request){$data=$this->segmentPayload($request);$segment=CustomerSegment::create([...$data,'uuid'=>(string)Str::uuid(),'slug'=>$this->uniqueSlug(CustomerSegment::class,$data['name']),'created_by'=>auth()->id()]);if($request->has('customer_ids'))$segment->customers()->sync($request->input('customer_ids',[]));AuditTrail::record('customer-segment.created',$segment,null,$segment->toArray());return redirect()->route('admin.customer-segments.index',['selected'=>$segment->id])->with('success','Customer segment created.');}
    public function updateSegment(Request $request,CustomerSegment $segment){$before=$segment->toArray();$segment->update($this->segmentPayload($request));if($request->has('customer_ids'))$segment->customers()->sync($request->input('customer_ids',[]));AuditTrail::record('customer-segment.updated',$segment,$before,$segment->fresh()->toArray());return back()->with('success','Customer segment updated.');}
    public function duplicateSegment(CustomerSegment $segment){$copy=$segment->replicate(['uuid','slug']);$copy->uuid=(string)Str::uuid();$copy->name=$segment->name.' Copy';$copy->slug=$this->uniqueSlug(CustomerSegment::class,$copy->name);$copy->created_by=auth()->id();$copy->save();$copy->customers()->sync($segment->customers()->pluck('users.id')->all());AuditTrail::record('customer-segment.duplicated',$copy,null,$copy->toArray());return redirect()->route('admin.customer-segments.index',['selected'=>$copy->id])->with('success','Segment duplicated.');}
    public function destroySegment(CustomerSegment $segment){AuditTrail::record('customer-segment.deleted',$segment,$segment->toArray(),null);$segment->delete();return redirect()->route('admin.customer-segments.index')->with('success','Customer segment deleted.');}

    public function exportSegments():StreamedResponse
    {
        return response()->streamDownload(function(){$out=fopen('php://output','w');fputcsv($out,['name','type','description','country','status','uuid']);CustomerSegment::orderBy('id')->chunk(250,function($segments)use($out){foreach($segments as $s)fputcsv($out,[$s->name,$s->type,$s->description,$s->country,$s->is_active?'active':'inactive',$s->uuid]);});fclose($out);},'customer-segments.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function importSegments(Request $request)
    {
        $file=$this->csv($request);$count=0;$h=fopen($file->getRealPath(),'r');$header=array_map('trim',fgetcsv($h)?:[]);while(($row=fgetcsv($h))!==false){if(count($row)!==count($header))continue;$d=array_combine($header,$row);if(empty($d['name']))continue;$payload=['type'=>in_array($d['type']??'custom',self::SEGMENT_TYPES,true)?$d['type']:'custom','description'=>$d['description']??null,'country'=>!empty($d['country'])?strtoupper($d['country']):null,'is_active'=>($d['status']??'active')!=='inactive'];$segment=CustomerSegment::where('name',$d['name'])->first();if($segment)$segment->update($payload);else CustomerSegment::create([...$payload,'name'=>$d['name'],'uuid'=>!empty($d['uuid'])?$d['uuid']:(string)Str::uuid(),'slug'=>$this->uniqueSlug(CustomerSegment::class,$d['name']),'created_by'=>auth()->id()]);$count++;}fclose($h);AuditTrail::record('customer-segments.imported',null,null,['count'=>$count]);return back()->with('success',$count.' segments imported.');
    }

    private function applyCustomerFilters(Builder $query,Request $request):void
    {
        $q=trim((string)$request->query('q',''));if($q!=='')$query->where(fn(Builder $x)=>$x->where('name','like','%'.$q.'%')->orWhere('email','like','%'.$q.'%')->orWhere('phone','like','%'.$q.'%')->orWhereRaw('CAST(public_uuid AS TEXT) LIKE ?', ['%'.$q.'%'])->orWhereHas('customerProfile',fn($p)=>$p->whereRaw('CAST(uuid AS TEXT) LIKE ?', ['%'.$q.'%'])));
        if($request->filled('group'))$query->whereHas('customerGroups',fn($x)=>$x->where('customer_groups.id',(int)$request->query('group')));if($request->filled('segment'))$query->whereHas('customerSegments',fn($x)=>$x->where('customer_segments.id',(int)$request->query('segment')));if($request->filled('status'))$query->whereHas('customerProfile',fn($x)=>$x->where('account_status',(string)$request->query('status')));if($request->filled('country'))$query->whereHas('customerProfile',fn($x)=>$x->where('country',(string)$request->query('country')));
    }

    private function selectedCustomer(Request $request,array $pageItems):?User
    {
        $id=(int)$request->query('selected',0);$candidate=$id>0?User::where('is_admin',false)->find($id):($pageItems[0]??null);return $candidate?User::whereKey($candidate->id)->where('is_admin',false)->with(['customerProfile.primaryGroup','customerGroups','customerSegments','orders.items','addresses','rewards'])->first():null;
    }

    private function customerProfileData(?User $customer):array
    {
        if(!$customer)return ['orders'=>collect(),'order_types'=>collect(),'returns'=>collect(),'communications'=>collect(),'activities'=>collect(),'top_categories'=>collect(),'reward_points'=>0];
        $orders=$customer->orders()->latest()->get();$orderTypes=$customer->orders()->selectRaw('order_type, COUNT(*) as count, COALESCE(SUM(total),0) as total')->groupBy('order_type')->get();$returns=ReturnRequest::where('user_id',$customer->id)->latest()->limit(8)->get();$communications=Conversation::where(fn($q)=>$q->where('contact',$customer->email)->when($customer->phone,fn($x)=>$x->orWhere('contact',$customer->phone)))->latest()->limit(8)->get();$activities=AuditLog::where('subject_type',$customer->getMorphClass())->where('subject_id',$customer->id)->latest()->limit(8)->get();$topCategories=DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')->leftJoin('products','products.id','=','order_items.product_id')->leftJoin('categories','categories.id','=','products.category_id')->where('orders.user_id',$customer->id)->selectRaw("COALESCE(categories.name, 'Other') as name, SUM(order_items.quantity) as quantity")->groupBy('categories.name')->orderByDesc('quantity')->limit(5)->get();return ['orders'=>$orders,'order_types'=>$orderTypes,'returns'=>$returns,'communications'=>$communications,'activities'=>$activities,'top_categories'=>$topCategories,'reward_points'=>(int)$customer->rewards->sum('points')];
    }

    private function groupData(?CustomerGroup $group):array
    {
        if(!$group)return ['orders'=>0,'spent'=>0,'aov'=>0,'last_order'=>null,'activities'=>collect()];$ids=$group->customers()->pluck('users.id');$orders=Order::whereIn('user_id',$ids);$count=(int)(clone $orders)->count();$spent=(float)(clone $orders)->sum('total');return ['orders'=>$count,'spent'=>$spent,'aov'=>$count?$spent/$count:0,'last_order'=>(clone $orders)->latest()->value('created_at'),'activities'=>AuditLog::where('subject_type',$group->getMorphClass())->where('subject_id',$group->id)->latest()->limit(8)->get()];
    }

    private function segmentData(?CustomerSegment $segment,int $totalCustomers):array
    {
        if(!$segment)return ['orders'=>0,'spent'=>0,'aov'=>0,'conversion'=>0,'coverage'=>0,'activities'=>collect()];$ids=$segment->customers()->pluck('users.id');$orders=Order::whereIn('user_id',$ids);$count=(int)(clone $orders)->count();$spent=(float)(clone $orders)->sum('total');$members=$ids->count();$buyers=$members?User::whereIn('id',$ids)->whereHas('orders')->count():0;return ['orders'=>$count,'spent'=>$spent,'aov'=>$count?$spent/$count:0,'conversion'=>$members?($buyers/$members)*100:0,'coverage'=>$totalCustomers?($members/$totalCustomers)*100:0,'activities'=>AuditLog::where('subject_type',$segment->getMorphClass())->where('subject_id',$segment->id)->latest()->limit(8)->get()];
    }

    private function groupPayload(Request $request):array
    {
        $data=$request->validate(['name'=>['required','string','max:160'],'type'=>['required',Rule::in(self::GROUP_TYPES)],'description'=>['nullable','string','max:2000'],'pricing_rule'=>['nullable','string','max:160'],'discount_note'=>['nullable','string','max:160'],'country'=>['nullable','string','size:2'],'is_active'=>['nullable','boolean'],'is_vip'=>['nullable','boolean'],'customer_ids'=>['nullable','array'],'customer_ids.*'=>['integer','exists:users,id']]);unset($data['customer_ids']);$data['is_active']=$request->boolean('is_active');$data['is_vip']=$request->boolean('is_vip');$data['country']=!empty($data['country'])?strtoupper($data['country']):null;return $data;
    }

    private function segmentPayload(Request $request):array
    {
        $data=$request->validate(['name'=>['required','string','max:160'],'type'=>['required',Rule::in(self::SEGMENT_TYPES)],'description'=>['nullable','string','max:2000'],'country'=>['nullable','string','size:2'],'rule_definition'=>['nullable','string','max:4000'],'is_active'=>['nullable','boolean'],'customer_ids'=>['nullable','array'],'customer_ids.*'=>['integer','exists:users,id']]);unset($data['customer_ids']);$data['is_active']=$request->boolean('is_active');$data['country']=!empty($data['country'])?strtoupper($data['country']):null;$data['rule_definition']=filled($data['rule_definition']??null)?['expression'=>$data['rule_definition']]:null;return $data;
    }

    private function csv(Request $request):UploadedFile{return $request->validate(['file'=>['required','file','mimes:csv,txt','max:5120']])['file'];}
    private function perPage(Request $request):int{$value=(int)$request->query('per_page',10);return in_array($value,[10,20,50,100],true)?$value:10;}
    private function selectedModel(string $class,Request $request,array $pageItems){$id=(int)$request->query('selected',0);return $id>0?$class::find($id):($pageItems[0]??null);}
    private function uniqueSlug(string $class,string $name):string{$base=Str::slug($name)?:'record';$slug=$base;$i=2;while($class::where('slug',$slug)->exists())$slug=$base.'-'.$i++;return $slug;}
}
