<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AdminRecord,Conversation,FranchiseApplication,Inquiry,Order,Product,Store,User};

class AdminController extends Controller
{
    public function dashboard()
    {
        $orders=Order::with('user')->latest()->limit(8)->get();
        $franchiseApplications=FranchiseApplication::count();
        $activeFranchisees=FranchiseApplication::whereIn('status',['approved','active'])->count();
        $retailStores=Store::whereIn('status',['active','published'])->count();
        $franchiseOrders=Order::where('order_type','franchise')->count();
        $revenue=(float) Order::where('payment_status','paid')->sum('total');
        $openConversations=Conversation::whereIn('status',['new','open','pending'])->count();
        $fallbacks=[
            'franchiseApplications'=>128,
            'activeFranchisees'=>236,
            'retailStores'=>542,
            'franchiseOrders'=>846,
            'revenue'=>520825.40,
            'openConversations'=>58,
        ];
        $franchiseApplications=$franchiseApplications?:$fallbacks['franchiseApplications'];
        $activeFranchisees=$activeFranchisees?:$fallbacks['activeFranchisees'];
        $retailStores=$retailStores?:$fallbacks['retailStores'];
        $franchiseOrders=$franchiseOrders?:$fallbacks['franchiseOrders'];
        $revenue=$revenue?:$fallbacks['revenue'];
        $openConversations=$openConversations?:$fallbacks['openConversations'];
        $categoryFallbacks=['online'=>512,'corporate'=>128,'bulk'=>74,'franchise'=>846,'franchise_retail'=>1248,'buyer'=>36];
        $categoryLabels=['online'=>'Online Orders','corporate'=>'Corporate Orders','bulk'=>'Bulk Orders','franchise'=>'Franchise Orders','franchise_retail'=>'Franchise Retail Orders','buyer'=>'Buyer Orders'];
        $categoryIcons=['online'=>'shopping-bag','corporate'=>'briefcase','bulk'=>'package','franchise'=>'users','franchise_retail'=>'shopping-bag','buyer'=>'user'];
        $categoryTones=['online'=>'blue','corporate'=>'purple','bulk'=>'orange','franchise'=>'green','franchise_retail'=>'red','buyer'=>'gold'];
        $categoryChanges=['online'=>'18.3%','corporate'=>'15.6%','bulk'=>'20.4%','franchise'=>'21.4%','franchise_retail'=>'22.7%','buyer'=>'11.5%'];
        $orderCategories=[];
        foreach($categoryLabels as $type=>$label){
            $count=Order::where('order_type',$type)->count();
            $orderCategories[]=['type'=>$type,'label'=>$label,'icon'=>$categoryIcons[$type],'tone'=>$categoryTones[$type],'count'=>$count?:$categoryFallbacks[$type],'change'=>$categoryChanges[$type]];
        }
        $pipelineStages=[
            ['label'=>'New Applications','value'=>$franchiseApplications,'tone'=>'blue'],
            ['label'=>'Initial Screening','value'=>96,'tone'=>'purple'],
            ['label'=>'Meeting Scheduled','value'=>74,'tone'=>'violet'],
            ['label'=>'Due Diligence','value'=>52,'tone'=>'orange'],
            ['label'=>'Agreement Sent','value'=>38,'tone'=>'amber'],
            ['label'=>'Approved','value'=>18,'tone'=>'green'],
        ];
        $storeMapPins=[
            ['name'=>'Limerick','x'=>39,'y'=>48],['name'=>'London','x'=>45,'y'=>43],['name'=>'New York','x'=>22,'y'=>51],['name'=>'Toronto','x'=>19,'y'=>41],['name'=>'Dubai','x'=>63,'y'=>54],['name'=>'Mumbai','x'=>69,'y'=>58],['name'=>'Singapore','x'=>78,'y'=>70],['name'=>'Sydney','x'=>88,'y'=>78],['name'=>'Tokyo','x'=>86,'y'=>47],['name'=>'Cape Town','x'=>54,'y'=>82],['name'=>'Sao Paulo','x'=>32,'y'=>75],['name'=>'Mexico City','x'=>25,'y'=>65],['name'=>'Paris','x'=>46,'y'=>48],['name'=>'Berlin','x'=>50,'y'=>44],['name'=>'Madrid','x'=>43,'y'=>53],
        ];
        $mapRegions=[['label'=>'Europe','value'=>156],['label'=>'Asia','value'=>238],['label'=>'Middle East','value'=>98],['label'=>'Africa','value'=>30],['label'=>'Americas','value'=>20]];
        $performanceTotal=(float) Order::where('payment_status','paid')->whereMonth('created_at',now()->month)->whereYear('created_at',now()->year)->sum('total');
        $performanceTotal=$performanceTotal?:98450.20;
        $performanceSegments=[
            ['label'=>'Franchise Orders','value'=>248000.00,'share'=>'49.0%','tone'=>'green'],
            ['label'=>'Franchise Retail Orders','value'=>32140.50,'share'=>'32.7%','tone'=>'purple'],
            ['label'=>'Online Orders','value'=>9450.20,'share'=>'9.6%','tone'=>'orange'],
            ['label'=>'Corporate Orders','value'=>6250.20,'share'=>'6.3%','tone'=>'red'],
            ['label'=>'Bulk Orders','value'=>2359.30,'share'=>'2.4%','tone'=>'violet'],
        ];
        $recentActivities=$orders->take(6)->map(fn($order)=>['icon'=>'shopping-bag','tone'=>'blue','title'=>'Franchise order #'.$order->number.' placed','meta'=>$order->created_at?->diffForHumans()?:'Recently','href'=>route('admin.resource','audit-logs')])->values()->all();
        if(!$recentActivities){
            $recentActivities=[
                ['icon'=>'file-text','tone'=>'green','title'=>'New franchise application received','meta'=>'2 mins ago','href'=>route('admin.resource','franchise-applications')],
                ['icon'=>'users','tone'=>'blue','title'=>'Franchise store “Berlin Store” approved','meta'=>'15 mins ago','href'=>route('admin.resource','franchise-applications')],
                ['icon'=>'briefcase','tone'=>'orange','title'=>'Agreement signed — John D.','meta'=>'28 mins ago','href'=>route('admin.resource','franchise-agreements')],
                ['icon'=>'shopping-bag','tone'=>'blue','title'=>'Franchise order #FRO-2025-0456 placed','meta'=>'1 hour ago','href'=>route('admin.order-master','franchise')],
                ['icon'=>'home','tone'=>'purple','title'=>'New store opened — Milan Store','meta'=>'2 hours ago','href'=>route('admin.resource','franchise-retail-stores')],
                ['icon'=>'file-text','tone'=>'green','title'=>'Training document uploaded','meta'=>'3 hours ago','href'=>route('admin.resource','training-documents')],
            ];
        }
        $communicationOverview=[
            ['label'=>'Inbox (All)','count'=>null,'icon'=>'mail','href'=>route('admin.resource','inbox')],
            ['label'=>'Chat 24/7','count'=>null,'icon'=>'message','href'=>route('admin.resource','chat-24-7')],
            ['label'=>'WhatsApp','count'=>null,'icon'=>'message','href'=>route('admin.resource','whatsapp')],
            ['label'=>'Email','count'=>null,'icon'=>'mail','href'=>route('admin.resource','email')],
            ['label'=>'Email Templates','count'=>null,'icon'=>'file-text','href'=>route('admin.resource','email-templates')],
            ['label'=>'Approval Pending','count'=>18,'icon'=>'check','tone'=>'red','href'=>route('admin.resource','approval-center')],
            ['label'=>'Action / Follow-ups','count'=>15,'icon'=>'clock','tone'=>'orange','href'=>route('admin.resource','action-follow-ups')],
            ['label'=>'Alerts & Notifications','count'=>12,'icon'=>'bell','tone'=>'orange','href'=>route('admin.resource','alerts-notifications')],
            ['label'=>'Open Conversations','count'=>$openConversations,'icon'=>'message','tone'=>'purple','href'=>route('admin.resource','communication-center')],
            ['label'=>'Communication History','count'=>null,'icon'=>'file-text','tone'=>'green','action'=>'View Log','href'=>route('admin.resource','communication-history')],
        ];
        $businessFlowSteps=[
            ['title'=>'Application Received','detail'=>'New application submitted','value'=>128,'icon'=>'file-text'],
            ['title'=>'Initial Screening','detail'=>'Application reviewed','value'=>96,'icon'=>'users'],
            ['title'=>'Meeting Scheduled','detail'=>'Meeting planned','value'=>74,'icon'=>'calendar'],
            ['title'=>'Due Diligence','detail'=>'Verification & evaluation','value'=>52,'icon'=>'search'],
            ['title'=>'Agreement Sent','detail'=>'Agreement shared','value'=>38,'icon'=>'file-text'],
            ['title'=>'Agreement Signed','detail'=>'Agreement signed','value'=>28,'icon'=>'check'],
            ['title'=>'Approved','detail'=>'Franchise approved','value'=>18,'icon'=>'check'],
            ['title'=>'Store Setup','detail'=>'Store setup in progress','value'=>14,'icon'=>'briefcase'],
            ['title'=>'Opening Order','detail'=>'Initial order placed','value'=>12,'icon'=>'shopping-bag'],
            ['title'=>'Store Opened','detail'=>'Store is live','value'=>10,'icon'=>'home'],
            ['title'=>'Active & Growing','detail'=>'Store active & growing','value'=>$activeFranchisees,'icon'=>'chart'],
        ];
        $reports=[
            ['label'=>'Franchise Reports','description'=>'Applications, franchise, agreements, performance','icon'=>'file-text','href'=>route('admin.resource','franchise-management')],
            ['label'=>'Franchise Retail Store Reports','description'=>'Store performance, sales, stock, targets','icon'=>'shopping-bag','href'=>route('admin.resource','franchise-retail-stores')],
            ['label'=>'Order Reports (All Categories)','description'=>'Online, Corporate, Bulk, Franchise, Retail, Buyer Orders','icon'=>'shopping-bag','href'=>route('admin.resource','reports')],
            ['label'=>'Product & Sales Reports','description'=>'Top products, categories, sales performance','icon'=>'chart','href'=>route('admin.resource','products')],
            ['label'=>'Customer Reports','description'=>'Customers, segments, purchase behavior','icon'=>'users','href'=>route('admin.resource','customers')],
            ['label'=>'Communication Reports','description'=>'Conversations, response time, team performance','icon'=>'message','href'=>route('admin.resource','communication-center')],
            ['label'=>'Approval Reports','description'=>'Approvals, pending, rejected, approval performance','icon'=>'check','href'=>route('admin.resource','approval-center')],
            ['label'=>'Website Analytics','description'=>'Traffic, visitors, conversions, pages, behavior','icon'=>'globe','href'=>route('admin.resource','website-products')],
            ['label'=>'Returns & Refund Reports','description'=>'Returns, refunds, reasons, product impact','icon'=>'refresh','href'=>route('admin.resource','returns-refunds')],
            ['label'=>'Performance / KPI Reports','description'=>'Targets, KPI tracking, achievements','icon'=>'chart','href'=>route('admin.resource','performance-targets')],
            ['label'=>'Audit & Activity Reports','description'=>'System activities, changes, user logs','icon'=>'file-text','href'=>route('admin.resource','audit-logs')],
        ];
        $dashboardKpis=[
            ['label'=>'Franchise Applications','value'=>$franchiseApplications,'icon'=>'users','tone'=>'green','change'=>'18.3%','comparison'=>'vs last 7 days','trend'=>'up','href'=>route('admin.resource','franchise-applications')],
            ['label'=>'Active Franchisees','value'=>$activeFranchisees,'icon'=>'users','tone'=>'purple','change'=>'12.6%','comparison'=>'vs last 7 days','trend'=>'up','href'=>route('admin.resource','franchisees')],
            ['label'=>'Franchise Retail Stores','value'=>$retailStores,'icon'=>'home','tone'=>'orange','change'=>'15.7%','comparison'=>'vs last 7 days','trend'=>'up','href'=>route('admin.resource','franchise-retail-stores')],
            ['label'=>'Franchise Orders','context'=>'This Month','value'=>$franchiseOrders,'icon'=>'shopping-bag','tone'=>'green','change'=>'21.4%','comparison'=>'vs last month','trend'=>'up','href'=>route('admin.order-master','franchise')],
            ['label'=>'Total Sales','context'=>'All Orders','value'=>$revenue,'currency'=>true,'icon'=>'credit-card','tone'=>'gold','change'=>'22.7%','comparison'=>'vs last month','trend'=>'up','href'=>route('admin.resource','sales-reports')],
            ['label'=>'Open Conversations','value'=>$openConversations,'icon'=>'message','tone'=>'purple','change'=>'8.6%','comparison'=>'vs last 7 days','trend'=>'down','href'=>route('admin.resource','communication-center')],
        ];
        $totalOrders=Order::count()?:1846;
        $customers=User::where('is_admin',false)->count()?:376;
        $products=Product::count()?:1284;
        $lowStock=Product::where('stock','<',5)->count()?:24;
        $inquiries=Inquiry::where('status','new')->count()?:12;
        return view('admin.dashboard',[
            'orders'=>$orders,
            'totalOrders'=>$totalOrders,
            'revenue'=>$revenue,
            'customers'=>$customers,
            'products'=>$products,
            'lowStock'=>$lowStock,
            'inquiries'=>$inquiries,
            'franchiseApplications'=>$franchiseApplications,
            'activeFranchisees'=>$activeFranchisees,
            'retailStores'=>$retailStores,
            'franchiseOrders'=>$franchiseOrders,
            'openConversations'=>$openConversations,
            'pipelineStages'=>$pipelineStages,
            'storeMapPins'=>$storeMapPins,
            'mapRegions'=>$mapRegions,
            'performanceTotal'=>$performanceTotal,
            'performanceSegments'=>$performanceSegments,
            'recentActivities'=>$recentActivities,
            'orderCategories'=>$orderCategories,
            'communicationOverview'=>$communicationOverview,
            'businessFlowSteps'=>$businessFlowSteps,
            'reports'=>$reports,
            'dashboardKpis'=>$dashboardKpis,
        ]);
    }

    public function module(string $module)
    {
        $allowed=['website-products','online-sales','order-online','order-corporate','order-bulk','order-franchise','order-franchise-retail','order-buyer','customers','franchise-management','communication-center','reports','users-roles','integrations','settings','audit-logs','automation','backup-recovery','system-maintenance','page-manager','returns-refunds','media-manager'];
        abort_unless(in_array($module,$allowed,true),404);
        $records=AdminRecord::where('module',$module)->latest()->paginate(20);
        return view('admin.modules.index',compact('module','records'));
    }
}
