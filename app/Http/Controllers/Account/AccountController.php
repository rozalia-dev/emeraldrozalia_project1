<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\{AddressRequest, ProfileUpdateRequest, ReturnRequestRequest};
use App\Models\{Address, Order, PaymentTransaction, ReturnRequest};
use App\Services\{AuditTrail, ReturnRequestService};
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['verified'];
    }

    public function dashboard(): View
    {
        $user = auth()->user();

        return view('site.account', [
            'orders' => $user->orders()->with('items')->latest()->limit(8)->get(),
            'ordersCount' => $user->orders()->count(),
            'rewards' => $user->rewards()->sum('points'),
            'wishlistCount' => $user->wishlistItems()->count(),
            'returnsCount' => ReturnRequest::query()->where('user_id', $user->id)->count(),
        ]);
    }

    public function section(string $section): View
    {
        $allowed = ['orders', 'wishlist', 'rewards', 'addresses', 'profile', 'payments', 'designs', 'bulk-orders', 'returns'];
        abort_unless(in_array($section, $allowed, true), 404);

        $user = auth()->user();

        return view('account.section', [
            'section' => $section,
            'orders' => $user->orders()->with(['items', 'payments'])->latest()->get(),
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

    public function profile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = auth()->user();
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
