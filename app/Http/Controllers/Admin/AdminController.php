<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AdminRecord,Conversation,FranchiseApplication,Inquiry,Order,Product,Store,User};
use App\Services\AdminDashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(Request $request, AdminDashboardMetricsService $metrics): View
    {
        return view('admin.dashboard', $metrics->build($request));
    }

    public function search(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $results = [];

        if ($q !== '') {
            $like = "%{$q}%";
            $results = [
                'Products' => Product::query()
                    ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('brand', 'like', $like))
                    ->orderBy('name')->limit(8)->get()
                    ->map(fn (Product $product): array => ['label' => $product->name, 'detail' => $product->sku.' · €'.number_format((float) $product->price, 2), 'href' => route('product', $product), 'icon' => 'package'])
                    ->all(),
                'Online records' => AdminRecord::query()
                    ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('reference', 'like', $like))
                    ->latest()->limit(8)->get()
                    ->map(fn (AdminRecord $record): array => ['label' => $record->title, 'detail' => str($record->module)->headline().' · '.($record->reference ?: 'No reference'), 'href' => route('admin.resource', $record->module), 'icon' => 'file-text'])
                    ->all(),
                'Orders' => Order::query()
                    ->where(fn ($query) => $query->where('number', 'like', $like)->orWhere('email', 'like', $like))
                    ->latest()->limit(8)->get()
                    ->map(fn (Order $order): array => ['label' => $order->number, 'detail' => str($order->order_type)->headline().' · '.$order->email, 'href' => route('admin.order-master.show', [$order->order_type, $order]), 'icon' => 'shopping-bag'])
                    ->all(),
                'Franchise applications' => FranchiseApplication::query()
                    ->where(fn ($query) => $query->where('applicant_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('territory', 'like', $like))
                    ->latest()->limit(8)->get()
                    ->map(fn (FranchiseApplication $application): array => ['label' => $application->applicant_name, 'detail' => $application->territory.' · '.str($application->status)->headline(), 'href' => route('admin.resource', 'franchise-applications'), 'icon' => 'users'])
                    ->all(),
                'Communication' => Conversation::query()
                    ->where(fn ($query) => $query->where('contact', 'like', $like)->orWhere('subject', 'like', $like))
                    ->latest()->limit(8)->get()
                    ->map(fn (Conversation $conversation): array => ['label' => $conversation->subject ?: 'Customer conversation', 'detail' => $conversation->contact.' · '.str($conversation->status)->headline(), 'href' => route('admin.resource', 'communication-center'), 'icon' => 'message'])
                    ->all(),
                'Retail stores' => Store::query()
                    ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('business_name', 'like', $like))
                    ->latest()->limit(8)->get()
                    ->map(fn (Store $store): array => ['label' => $store->name, 'detail' => ($store->business_name ?: 'Franchise retail store').' · '.str($store->status)->headline(), 'href' => route('admin.resource', 'franchise-retail-stores'), 'icon' => 'home'])
                    ->all(),
            ];
            $results = array_filter($results, static fn (array $items): bool => $items !== []);
        }

        return view('admin.search', compact('q', 'results'));
    }

    public function module(string $module)
    {
        $allowed=['website-products','online-sales','order-online','order-corporate','order-bulk','order-franchise','order-franchise-retail','order-buyer','customers','franchise-management','communication-center','reports','users-roles','integrations','settings','audit-logs','automation','backup-recovery','system-maintenance','page-manager','returns-refunds','media-manager'];
        abort_unless(in_array($module,$allowed,true),404);
        $records=AdminRecord::where('module',$module)->latest()->paginate(20);
        return view('admin.modules.index',compact('module','records'));
    }
}
