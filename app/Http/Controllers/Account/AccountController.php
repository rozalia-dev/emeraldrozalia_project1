<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\{Address, Order, PaymentTransaction, ReturnRequest};
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        return view('site.account', [
            'orders' => auth()->user()->orders()->with('items')->latest()->limit(8)->get(),
            'rewards' => auth()->user()->rewards()->sum('points'),
            'wishlistCount' => auth()->user()->wishlistItems()->count(),
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
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        auth()->user()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function addressStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'line1' => ['required', 'string', 'max:180'],
            'line2' => ['nullable', 'string', 'max:180'],
            'city' => ['required', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'size:2'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            auth()->user()->addresses()->update(['is_default' => false]);
        }

        auth()->user()->addresses()->create($data + ['is_default' => $request->boolean('is_default')]);

        return back()->with('success', 'Address saved.');
    }

    public function addressDelete(Address $address): RedirectResponse
    {
        abort_unless($address->user_id === auth()->id(), 403);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    public function returnStore(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if (!in_array($order->status, ['shipped', 'completed'], true)) {
            throw ValidationException::withMessages([
                'order' => 'Returns and exchanges can be requested after an order has shipped.',
            ]);
        }

        if (ReturnRequest::query()
            ->where('user_id', auth()->id())
            ->where('order_id', $order->id)
            ->whereIn('status', ['requested', 'approved', 'received', 'inspecting'])
            ->exists()) {
            throw ValidationException::withMessages([
                'order' => 'An open return or exchange already exists for this order.',
            ]);
        }

        $data = $request->validate([
            'type' => ['required', 'in:return,exchange'],
            'reason' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        $return = ReturnRequest::create($data + [
            'user_id' => auth()->id(),
            'order_id' => $order->id,
            'number' => 'RET-'.strtoupper(Str::random(8)),
        ]);
        AuditTrail::record('customer.return_requested', $return, null, $return->toArray());

        return back()->with('success', 'Return/exchange request submitted.');
    }

    public function invoice(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $order->load(['items', 'payments']);

        return view('account.invoice', compact('order'));
    }
}
