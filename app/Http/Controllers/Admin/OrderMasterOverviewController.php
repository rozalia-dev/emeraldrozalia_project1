<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderMasterOverviewController extends Controller
{
    private const TYPES = [
        'online' => 'Online Orders', 'corporate' => 'Corporate Orders', 'bulk' => 'Bulk Orders',
        'franchise' => 'Franchise Orders', 'franchise_retail' => 'Franchise Retail Orders', 'buyer' => 'Buyer Orders',
    ];

    private const TYPE_META = [
        'online' => ['label'=>'Online Orders','prefix'=>'ONL','tone'=>'green','icon'=>'globe'],
        'corporate' => ['label'=>'Corporate Orders','prefix'=>'CORP','tone'=>'blue','icon'=>'briefcase'],
        'bulk' => ['label'=>'Bulk Orders','prefix'=>'BULK','tone'=>'purple','icon'=>'package'],
        'franchise' => ['label'=>'Franchise Orders','prefix'=>'FRAN','tone'=>'orange','icon'=>'shopping-bag'],
        'franchise_retail' => ['label'=>'Franchise Retail Orders','prefix'=>'FRRET','tone'=>'teal','icon'=>'shopping-bag'],
        'buyer' => ['label'=>'Buyer Orders','prefix'=>'BUYR','tone'=>'red','icon'=>'user'],
    ];

    private const ORDER_STATUSES = ['pending','approved','processing','shipped','completed','cancelled','refunded'];
    private const PAYMENT_STATUSES = ['unpaid','pending','paid','failed','refunded','pay_on_delivery'];
    private const FULFILLMENT_STATUSES = ['pending','on_hold','picking','packed','ready_to_ship','shipped','delivered','closed'];

    public function index(Request $request): View
    {
        $perPage = in_array((int)$request->query('per_page',10), [10,20,50,100], true) ? (int)$request->query('per_page',10) : 10;
        $orders = $this->filter(Order::query()->with(['user','items']), $request)->latest()->paginate($perPage)->withQueryString();
        $totalOrders = (int)Order::query()->count();
        $totalValue = (float)(Order::query()->sum('total') ?: 0);

        $categoryStats = collect(self::TYPE_META)->map(function(array $meta,string $type){
            $base=Order::query()->where('order_type',$type);
            $count=(int)(clone $base)->count(); $value=(float)((clone $base)->sum('total') ?: 0);
            $recent=(int)(clone $base)->where('created_at','>=',now()->subDays(30))->count();
            $previous=(int)(clone $base)->whereBetween('created_at',[now()->subDays(60),now()->subDays(30)])->count();
            $trend=$previous>0?(($recent-$previous)/$previous)*100:($recent>0?100:0);
            return [...$meta,'type'=>$type,'count'=>$count,'value'=>$value,'trend'=>round($trend,1)];
        });

        $statusCounts=[
            'new'=>(int)Order::query()->where('status','pending')->count(),
            'processing'=>(int)Order::query()->where('status','processing')->count(),
            'pending'=>(int)Order::query()->where(fn(Builder $q)=>$q->where('status','pending')->orWhereIn('payment_status',['unpaid','pending','failed']))->count(),
            'shipped'=>(int)Order::query()->where('status','shipped')->count(),
            'delivered'=>(int)Order::query()->where('status','completed')->count(),
            'cancelled'=>(int)Order::query()->where('status','cancelled')->count(),
            'return_refund'=>(int)Order::query()->where(fn(Builder $q)=>$q->where('status','refunded')->orWhereHas('returns'))->count(),
        ];
        $summary=[
            'total_orders'=>$totalOrders,'total_value'=>$totalValue,'average_value'=>$totalOrders?$totalValue/$totalOrders:0,
            'in_progress'=>(int)Order::query()->whereIn('status',['approved','processing','shipped'])->count(),
            'pending'=>$statusCounts['pending'],'shipped'=>$statusCounts['shipped'],'delivered'=>$statusCounts['delivered'],
            'cancelled'=>$statusCounts['cancelled'],'returns'=>(int)ReturnRequest::query()->count(),
            'conversion'=>$totalOrders?($statusCounts['delivered']/$totalOrders)*100:0,
        ];

        $topProducts=OrderItem::query()->whereIn('order_id',Order::query()->select('id'))
            ->selectRaw('product_id, name, sku, SUM(quantity) AS quantity_sum, SUM(total) AS revenue_sum')
            ->groupBy('product_id','name','sku')->orderByDesc('quantity_sum')->limit(5)->get();
        $monthlyValue=collect(self::TYPE_META)->map(fn(array $meta,string $type)=>[...$meta,'type'=>$type,'value'=>(float)(Order::query()->where('order_type',$type)->where('created_at','>=',now()->startOfMonth())->sum('total') ?: 0)]);
        $paymentMethods=Order::query()->whereNotNull('payment_method')->where('payment_method','<>','')->distinct()->orderBy('payment_method')->pluck('payment_method');

        return view('admin.orders.overview',[
            'orders'=>$orders,'categoryStats'=>$categoryStats,'statusCounts'=>$statusCounts,'summary'=>$summary,
            'topProducts'=>$topProducts,'monthlyValue'=>$monthlyValue,'notifications'=>$this->notifications($statusCounts),
            'typeMeta'=>self::TYPE_META,'orderStatuses'=>self::ORDER_STATUSES,'paymentStatuses'=>self::PAYMENT_STATUSES,
            'fulfillmentStatuses'=>self::FULFILLMENT_STATUSES,'paymentMethods'=>$paymentMethods,
            'activeTab'=>(string)$request->query('tab','all'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'order_type'=>['required',Rule::in(array_keys(self::TYPES))],'customer_name'=>['required','string','max:150'],
            'email'=>['required','email','max:255'],'phone'=>['nullable','string','max:40'],
            'subtotal'=>['required','numeric','min:0','max:999999999.99'],'shipping'=>['nullable','numeric','min:0','max:999999999.99'],
            'discount'=>['nullable','numeric','min:0','max:999999999.99'],'status'=>['required',Rule::in(self::ORDER_STATUSES)],
            'payment_status'=>['required',Rule::in(self::PAYMENT_STATUSES)],'payment_method'=>['nullable','string','max:80'],
            'fulfillment_status'=>['required',Rule::in(self::FULFILLMENT_STATUSES)],'notes'=>['nullable','string','max:2000'],
        ]);
        $subtotal=round((float)$data['subtotal'],2); $shipping=round((float)($data['shipping']??0),2); $discount=round((float)($data['discount']??0),2);
        $order=Order::create([
            'number'=>self::TYPE_META[$data['order_type']]['prefix'].'-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
            'order_type'=>$data['order_type'],'status'=>$data['status'],'payment_status'=>$data['payment_status'],'fulfillment_status'=>$data['fulfillment_status'],
            'subtotal'=>$subtotal,'shipping'=>$shipping,'discount'=>$discount,'total'=>round(max(0,$subtotal+$shipping-$discount),2),
            'currency'=>'EUR','currency_code'=>'EUR','exchange_rate'=>1,'email'=>$data['email'],'phone'=>$data['phone']??null,
            'payment_method'=>$data['payment_method']??null,'notes'=>$data['notes']??null,'shipping_address'=>['name'=>$data['customer_name']],
        ]);
        AuditTrail::record('order.created.admin',$order,null,$order->toArray());
        return redirect()->route('admin.order-master.overview')->with('success','Order created successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file'=>['required','file','mimes:csv,txt','max:4096']]);
        /** @var UploadedFile $file */ $file=$request->file('file');
        $handle=fopen($file->getRealPath(),'r'); if(!$handle)return back()->withErrors(['file'=>'Unable to read the CSV file.']);
        $header=array_map(fn($v)=>strtolower(trim((string)$v)),fgetcsv($handle) ?: []); $created=0; $updated=0;
        DB::transaction(function() use($handle,$header,&$created,&$updated){
            while(($row=fgetcsv($handle))!==false){
                if(count($row)!==count($header))continue; $data=array_combine($header,$row) ?: [];
                $type=trim((string)($data['order_type']??'online')); if(!isset(self::TYPES[$type]))continue;
                $number=trim((string)($data['number']??'')); $order=$number!==''?Order::query()->where('number',$number)->first():null;
                $subtotal=is_numeric($data['subtotal']??null)?(float)$data['subtotal']:0; $shipping=is_numeric($data['shipping']??null)?(float)$data['shipping']:0; $discount=is_numeric($data['discount']??null)?(float)$data['discount']:0;
                $payload=[
                    'number'=>$number!==''?$number:self::TYPE_META[$type]['prefix'].'-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                    'order_type'=>$type,'status'=>in_array($data['status']??'',self::ORDER_STATUSES,true)?$data['status']:'pending',
                    'payment_status'=>in_array($data['payment_status']??'',self::PAYMENT_STATUSES,true)?$data['payment_status']:'pending',
                    'fulfillment_status'=>in_array($data['fulfillment_status']??'',self::FULFILLMENT_STATUSES,true)?$data['fulfillment_status']:'pending',
                    'subtotal'=>round($subtotal,2),'shipping'=>round($shipping,2),'discount'=>round($discount,2),
                    'total'=>is_numeric($data['total']??null)?round((float)$data['total'],2):round(max(0,$subtotal+$shipping-$discount),2),
                    'currency'=>strtoupper(trim((string)($data['currency']??'EUR'))),'currency_code'=>strtoupper(trim((string)($data['currency_code']??$data['currency']??'EUR'))),'exchange_rate'=>1,
                    'email'=>trim((string)($data['email']??'')) ?: null,'phone'=>trim((string)($data['phone']??'')) ?: null,
                    'payment_method'=>trim((string)($data['payment_method']??'')) ?: null,'shipping_address'=>['name'=>trim((string)($data['customer_name']??'Imported Customer'))],
                ];
                if($order){$before=$order->toArray();$order->update($payload);AuditTrail::record('order.import.updated',$order,$before,$order->fresh()->toArray());$updated++;}
                else{$order=Order::create($payload);AuditTrail::record('order.import.created',$order,null,$order->toArray());$created++;}
            }
        }); fclose($handle);
        return redirect()->route('admin.order-master.overview')->with('success',"Import complete: {$created} created, {$updated} updated.");
    }

    public function export(Request $request): StreamedResponse
    {
        $query=$this->filter(Order::query()->with('user'),$request); $filename='order-master-'.now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function() use($query){
            $out=fopen('php://output','w'); fputcsv($out,['number','order_type','customer_name','email','phone','status','payment_status','payment_method','fulfillment_status','subtotal','shipping','discount','total','currency_code','created_at']);
            $query->orderBy('id')->chunk(250,function($orders) use($out){foreach($orders as $order)fputcsv($out,[$order->number,$order->order_type,$order->user?->name ?: data_get($order->shipping_address,'name','Guest Customer'),$order->email,$order->phone,$order->status,$order->payment_status,$order->payment_method,$order->fulfillment_status,$order->subtotal,$order->shipping,$order->discount,$order->total,$order->currency_code ?: $order->currency,optional($order->created_at)->toIso8601String()]);}); fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    private function filter(Builder $query, Request $request): Builder
    {
        $search=trim((string)$request->query('q',''));
        if($search!=='')$query->where(function(Builder $q) use($search){$q->where('number','like','%'.$search.'%')->orWhere('email','like','%'.$search.'%')->orWhere('phone','like','%'.$search.'%')->orWhere('shipping_address->name','like','%'.$search.'%')->orWhereHas('user',fn(Builder $u)=>$u->where('name','like','%'.$search.'%')->orWhere('email','like','%'.$search.'%'));});
        $type=(string)$request->query('order_type',''); if(isset(self::TYPES[$type]))$query->where('order_type',$type);
        $status=(string)$request->query('status',''); if(in_array($status,self::ORDER_STATUSES,true))$query->where('status',$status);
        $payment=(string)$request->query('payment_method',''); if($payment!=='')$query->where('payment_method',$payment);
        $paymentStatus=(string)$request->query('payment_status',''); if(in_array($paymentStatus,self::PAYMENT_STATUSES,true))$query->where('payment_status',$paymentStatus);
        $fulfillment=(string)$request->query('fulfillment_status',''); if(in_array($fulfillment,self::FULFILLMENT_STATUSES,true))$query->where('fulfillment_status',$fulfillment);
        if($request->filled('date_from'))$query->whereDate('created_at','>=',$request->query('date_from')); if($request->filled('date_to'))$query->whereDate('created_at','<=',$request->query('date_to'));
        switch((string)$request->query('tab','all')){
            case 'new':$query->where('status','pending');break; case 'processing':$query->where('status','processing');break;
            case 'pending':$query->where(fn(Builder $q)=>$q->where('status','pending')->orWhereIn('payment_status',['unpaid','pending','failed']));break;
            case 'shipped':$query->where('status','shipped');break; case 'delivered':$query->where('status','completed');break;
            case 'cancelled':$query->where('status','cancelled');break; case 'returns':$query->where(fn(Builder $q)=>$q->where('status','refunded')->orWhereHas('returns'));break;
        }
        return $query;
    }

    private function notifications(array $statusCounts): array
    {
        return [
            ['tone'=>'orange','text'=>number_format($statusCounts['pending']).' orders pending approval','time'=>'live'],
            ['tone'=>'blue','text'=>number_format((int)Order::query()->whereIn('payment_status',['unpaid','pending'])->count()).' orders pending payment','time'=>'live'],
            ['tone'=>'green','text'=>number_format((int)Order::query()->where('status','shipped')->whereDate('updated_at',today())->count()).' orders shipped today','time'=>'today'],
            ['tone'=>'gray','text'=>number_format((int)ReturnRequest::query()->count()).' return requests received','time'=>'live'],
            ['tone'=>'red','text'=>number_format((int)Order::query()->where('status','cancelled')->whereDate('updated_at',today())->count()).' orders cancelled today','time'=>'today'],
            ['tone'=>'orange','text'=>'Low stock for '.number_format((int)Product::query()->whereBetween('stock',[1,10])->count()).' products','time'=>'live'],
            ['tone'=>'red','text'=>'Payment failed for '.number_format((int)Order::query()->where('payment_status','failed')->count()).' orders','time'=>'live'],
            ['tone'=>'red','text'=>number_format((int)Order::query()->where('total','>=',1000)->whereDate('created_at',today())->count()).' high value orders received','time'=>'today'],
        ];
    }
}
