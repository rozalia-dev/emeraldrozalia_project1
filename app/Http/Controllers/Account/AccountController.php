<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\{AddressRequest, ProfileUpdateRequest, ReturnRequestRequest};
use App\Models\{Address, FranchiseApplication, Order, PaymentTransaction, ReturnRequest, SalesQuote};
use App\Services\{AuditTrail, CustomerBusinessRecordLinker, ReturnRequestService};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(CustomerBusinessRecordLinker $linker): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->is_admin) {
            return redirect()->route('admin.profile.show');
        }

        $linker->claimFor($user);

        return view('site.account', [
            'orders' => $user->orders()->with('items')->latest()->limit(8)->get(),
            'ordersCount' => $user->orders()->count(),
            'corporateCount' => $this->quotesFor($user->id, 'corporate')->count(),
            'bulkCount' => $this->quotesFor($user->id, 'bulk')->count(),
            'franchiseCount' => FranchiseApplication::withoutGlobalScopes()->where('customer_id', $user->id)->count(),
            'rewards' => $user->rewards()->sum('points'),
            'wishlistCount' => $user->wishlistItems()->count(),
            'returnsCount' => ReturnRequest::query()->where('user_id', $user->id)->count(),
        ]);
    }

    public function section(string $section, CustomerBusinessRecordLinker $linker): View|RedirectResponse
    {
        $allowed = ['orders', 'wishlist', 'rewards', 'addresses', 'profile', 'payments', 'designs', 'corporate-orders', 'bulk-orders', 'franchise', 'returns'];
        abort_unless(in_array($section, $allowed, true), 404);

        $user = auth()->user();

        if ($user->is_admin) {
            return redirect()->route('admin.profile.show');
        }

        $linker->claimFor($user);

        $corporateQuotes = $this->quotesFor($user->id, 'corporate')->with(['order', 'conversation'])->latest()->get();
        $bulkQuotes = $this->quotesFor($user->id, 'bulk')->with(['order', 'conversation'])->latest()->get();
        $corporateOrders = $user->orders()->with(['items', 'payments'])->where('order_type', 'corporate')->latest()->get();
        $bulkOrders = $user->orders()->with(['items', 'payments'])->where('order_type', 'bulk')->latest()->get();
        $franchiseOrders = $user->orders()->with(['items', 'payments'])->whereIn('order_type', ['franchise', 'franchise_retail'])->latest()->get();
        $franchiseApplications = FranchiseApplication::withoutGlobalScopes()
            ->where('customer_id', $user->id)
            ->with(['conversation', 'inquiry'])
            ->latest()
            ->get();

        return view('account.section', [
            'section' => $section,
            'orders' => $user->orders()->with(['items', 'payments'])->latest()->get(),
            'corporateQuotes' => $corporateQuotes,
            'bulkQuotes' => $bulkQuotes,
            'corporateOrders' => $corporateOrders,
            'bulkOrders' => $bulkOrders,
            'franchiseApplications' => $franchiseApplications,
            'franchiseOrders' => $franchiseOrders,
            'addresses' => $user->addresses,
            'wishlist' => $user->wishlistItems()->with('product')->latest()->get(),
            'rewards' => $user->rewards()->latest()->get(),
            'payments' => PaymentTransaction::query()
                ->whereHas('order', fn ($query) => $query->where('user_id', $user->id))
                ->with('order')
                ->latest()
                ->get(),
            'returns' => ReturnRequest::query()
                ->where('user_id', $user->id)
                ->with('order')
                ->latest()
                ->get(),
            'wishlistCount' => $user->wishlistItems()->count(),
            'returnsCount' => ReturnRequest::query()->where('user_id', $user->id)->count(),
        ]);
    }

    private function quotesFor(int $customerId, string $orderType)
    {
        return SalesQuote::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->where('order_type', $orderType);
    }

    public function profile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user->is_admin) {
            return redirect()->route('admin.profile.show');
        }

        $before = $user->only(['name', 'phone']);
        $user->update($request->validated());
        AuditTrail::record('customer.profile_updated', $user, $before, $user->fresh()->only(['name', 'phone']));

        return back()->with('success', 'Profile updated.');
    }

    public function addressStore(AddressRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isDefault = $request->boolean('is_default');
        $address = null;
        DB::transaction(function () use (&$address, $data, $isDefault): void {
            $user = auth()->user();
            if ($isDefault) {
                $user->addresses()->lockForUpdate()->update(['is_default' => false]);
            }
            $address = $user->addresses()->create($data + ['is_default' => $isDefault]);
            AuditTrail::record('customer.address_created', $address, null, $address->toArray());
        });

        return back()->with('success', 'Address saved.');
    }

    public function addressDelete(Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);
        $before = $address->toArray();
        $address->delete();
        AuditTrail::record('customer.address_deleted', $address, $before, null);

        return back()->with('success', 'Address removed.');
    }

    public function returnStore(ReturnRequestRequest $request, Order $order, ReturnRequestService $returns): RedirectResponse
    {
        Gate::authorize('view', $order);
        $returns->submit($order, $request->validated());

        return back()->with('success', 'Return/exchange request submitted.');
    }

    public function invoice(Order $order): View
    {
        Gate::authorize('invoice', $order);
        $order->load(['items', 'payments']);

        return view('account.invoice', compact('order'));
    }
}
