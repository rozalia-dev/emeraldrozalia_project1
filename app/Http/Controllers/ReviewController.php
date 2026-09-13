<?php

namespace App\Http\Controllers;

use App\Models\{AdminRecord, Product, Review};
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'max:120'],
            'body' => ['nullable', 'max:2000'],
        ]);

        $settingsRecord = AdminRecord::query()
            ->where('module', 'system-settings')
            ->where('reference', 'application-settings')
            ->latest('id')
            ->first();
        $settings = $settingsRecord?->data ?? [];
        $minimumRating = (int) ($settings['minimum_review_rating'] ?? 0);
        $autoApprove = filter_var($settings['auto_approve_reviews'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requiresApproval = filter_var($settings['require_review_approval'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $status = ($autoApprove || !$requiresApproval) && (int) $data['rating'] >= $minimumRating ? 'approved' : 'pending';

        Review::updateOrCreate(
            ['user_id' => auth()->id(), 'product_id' => $product->id],
            $data + ['status' => $status],
        );

        return back()->with('success', $status === 'approved'
            ? 'Review published successfully.'
            : 'Review submitted for approval.');
    }
}
