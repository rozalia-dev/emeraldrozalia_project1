<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AdminRecord,Conversation,FranchiseApplication,Inquiry,Order,Product,Store,User};

class AdminController extends Controller
{
    public function dashboard()
    {
        $orders=Order::with('user')->latest()->limit(8)->get();
        return view('admin.dashboard',[
            'orders'=>$orders,
            'totalOrders'=>Order::count(),
            'revenue'=>(float) Order::where('payment_status','paid')->sum('total'),
            'customers'=>User::where('is_admin',false)->count(),
            'products'=>Product::count(),
            'lowStock'=>Product::where('stock','<',5)->count(),
            'inquiries'=>Inquiry::where('status','new')->count(),
            'franchiseApplications'=>FranchiseApplication::count(),
            'activeFranchisees'=>FranchiseApplication::whereIn('status',['approved','active'])->count(),
            'retailStores'=>Store::whereIn('status',['active','published'])->count(),
            'franchiseOrders'=>Order::where('order_type','franchise')->count(),
            'openConversations'=>Conversation::whereIn('status',['new','open','pending'])->count(),
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
