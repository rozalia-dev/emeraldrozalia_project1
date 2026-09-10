<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\VariantSetting;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariantBulkPriceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variants' => ['required', 'array', 'min:1'],
            'variants.*' => ['required', 'uuid'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        $variants = ProductVariant::query()
            ->whereHas('product')
            ->whereIn('public_uuid', array_unique($data['variants']))
            ->get();

        if ($variants->count() !== count(array_unique($data['variants']))) {
            throw ValidationException::withMessages(['variants' => 'One or more selected variants are unavailable.']);
        }

        $settings = VariantSetting::current();
        $price = round((float) $data['price'], (int) $settings->price_rounding);
        $comparePrice = filled($data['compare_price'] ?? null)
            ? round((float) $data['compare_price'], (int) $settings->price_rounding)
            : null;

        DB::transaction(function () use ($variants, $price, $comparePrice): void {
            foreach ($variants as $variant) {
                $before = $variant->toArray();
                $variant->update([
                    'price' => $price,
                    'compare_price' => $comparePrice,
                    'updated_by' => auth()->id(),
                ]);
                AuditTrail::record('variant.bulk.price.updated', $variant, $before, $variant->fresh()->toArray());
            }
        });

        return back()->with('success', $variants->count().' variant prices updated.');
    }
}
