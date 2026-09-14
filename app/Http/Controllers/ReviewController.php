<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewStoreRequest;
use App\Models\{AdminRecord, Product, Review};
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(ReviewStoreRequest $request, Product $product)
    {
        abort_unless($product->is_active, 404);
        $data = $request->validated();

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
