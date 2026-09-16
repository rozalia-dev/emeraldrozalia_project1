<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiscountBulkActionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['activate', 'pause', 'expire', 'archive'])],
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        DB::transaction(function () use ($ids, $action): void {
            $discounts = Discount::query()->whereIn('id', $ids)->lockForUpdate()->get();
            abort_unless($discounts->count() === count($ids), 422, 'One or more selected discounts are unavailable.');

            if ($action === 'activate' && $discounts->contains(fn (Discount $discount): bool => (bool) $discount->ends_at?->isPast())) {
                throw ValidationException::withMessages([
                    'ids' => 'An expired coupon must be edited with a new end date before it can be activated.',
                ]);
            }

            foreach ($discounts as $discount) {
                $before = $discount->toArray();
                match ($action) {
                    'activate' => $discount->update(['is_active' => true]),
                    'pause' => $discount->update(['is_active' => false]),
                    'expire' => $discount->update(['is_active' => false, 'ends_at' => now()]),
                    'archive' => $discount->delete(),
                };
                AuditTrail::record(
                    'admin.discount.bulk_'.$action,
                    $discount,
                    $before,
                    $action === 'archive' ? null : $discount->fresh()->toArray(),
                );
            }
        });

        return back()->with('success', 'Selected discount lifecycle action completed.');
    }
}
