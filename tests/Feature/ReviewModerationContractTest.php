<?php

namespace Tests\Feature;

use App\Models\{Product, Review, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewModerationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_moderation_reads_live_records_and_audits_status_changes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $product = Product::create([
            'name' => 'Moderated Contract Cap',
            'slug' => 'moderated-contract-cap',
            'sku' => 'MODERATED-CONTRACT-001',
            'price' => 30,
            'stock' => 4,
            'is_active' => true,
        ]);
        $review = Review::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 4,
            'title' => 'Good',
            'body' => 'A useful contract review.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.resource', 'reviews-ratings'))
            ->assertOk()
            ->assertSee('Moderated Contract Cap', false);

        $this->actingAs($admin)
            ->patch(route('admin.reviews.status', $review), ['status' => 'approved'])
            ->assertRedirect();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'review.status.updated',
            'subject_id' => (string) $review->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reviews.bulk-status'), [
                'ids' => [$review->id],
                'status' => 'flagged',
            ])
            ->assertRedirect();

        $this->assertSame('flagged', $review->fresh()->status);
    }
}
