<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
class OrderMasterController extends Controller { private const TYPES=['online','corporate','bulk','franchise','franchise_retail','buyer']; public function index(Request $r,string $type){abort_unless(in_array($type,self::TYPES,true),404);$orders=Order::with('user')->where('order_type',$type)->when($r->filled('status'),fn($q)=>$q->where('status',$r->status))->when($r->filled('q'),fn($q)=>$q->where(fn($x)=>$x->where('number','like','%'.$r->q.'%')->orWhere('email','like','%'.$r->q.'%')))->latest()->paginate(25)->withQueryString();return view('admin.orders.index',compact('orders','type'));} public function update(Request $r,string $type,Order $order){abort_unless(in_array($type,self::TYPES,true)&&$order->order_type===$type,404);$d=$r->validate(['status'=>'required|in:pending,approved,processing,shipped,completed,cancelled,refunded','payment_status'=>'required|in:unpaid,pending,pay_on_delivery,paid,failed,refunded']);$order->update($d);return back()->with('success','Order status updated.');} }
