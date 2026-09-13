<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReviewModerationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_page_exposes_real_tabs_actions_bulk_controls_and_settings_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Review Contract Customer']);
        $product = Product::create([
            'name' => 'Review Contract Cap',
            'slug' => 'review-contract-cap',
            'sku' => 'REVIEW-CONTRACT-001',
            'description' => 'Review moderation contract product.',
            'price' => 39.99,
            'stock' => 4,
            'status' => 'active',
            'is_active' => true,
        ]);
        $review = Review::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 4,
            'title' => 'Review contract record',
            'body' => 'This record is rendered from PostgreSQL data.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.resource', ['module' => 'reviews-ratings']));

        $response->assertOk()
            ->assertSee($review->public_uuid)
            ->assertSee(route('admin.reviews.bulk-status'), false)
            ->assertSee(route('admin.resource', ['module' => 'reviews-ratings', 'tab' => 'import']), false)
            ->assertSee(route('admin.settings.save', 'application-settings'), false)
            ->assertSee(route('admin.resource', ['module' => 'inbox', 'kind' => 'questions']), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Placeholder Chart', false);

        $this->actingAs($admin)
            ->get(route('admin.resource', ['module' => 'reviews-ratings', 'tab' => 'import']))
            ->assertOk()
            ->assertSee(route('admin.reviews.import'), false);
    }

    public function test_bulk_review_status_updates_selected_records_and_audits_each_change(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $product = Product::create([
            'name' => 'Bulk Review Cap',
            'slug' => 'bulk-review-cap',
            'sku' => 'BULK-REVIEW-001',
            'description' => 'Bulk moderation contract product.',
            'price' => 39.99,
            'stock' => 4,
            'status' => 'active',
            'is_active' => true,
        ]);
        $first = Review::create(['user_id' => $customer->id, 'product_id' => $product->id, 'rating' => 3, 'body' => 'First', 'status' => 'pending']);
        $secondCustomer = User::factory()->create();
        $second = Review::create(['user_id' => $secondCustomer->id, 'product_id' => $product->id, 'rating' => 5, 'body' => 'Second', 'status' => 'pending']);

        $this->actingAs($admin)->post(route('admin.reviews.bulk-status'), [
            'ids' => [$first->id, $second->id],
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('approved', $second->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.status.bulk_updated']);
    }

    public function test_review_csv_import_matches_existing_users_and_products_and_keeps_records_pending(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['email' => 'csv-review@example.test']);
        Product::create([
            'name' => 'CSV Review Cap',
            'slug' => 'csv-review-cap',
            'sku' => 'CSV-REVIEW-001',
            'description' => 'CSV review import product.',
            'price' => 29.99,
            'stock' => 10,
            'status' => 'active',
            'is_active' => true,
        ]);
        $csv = "email,product_sku,rating,title,body\ncsv-review@example.test,CSV-REVIEW-001,5,Imported review,Imported from CSV\n";

        $this->actingAs($admin)->post(route('admin.reviews.import'), [
            'file' => UploadedFile::fake()->createWithContent('reviews.csv', $csv),
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'user_id' => $customer->id,
            'rating' => 5,
            'title' => 'Imported review',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reviews.import.completed']);
    }

    public function test_review_settings_are_saved_through_the_shared_application_settings_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.save', 'application-settings'), [
            'tab' => 'features',
            'auto_approve_reviews' => '1',
            'require_review_approval' => '0',
            'allow_review_photos' => '1',
            'allow_review_videos' => '0',
            'verified_purchases_only' => '1',
            'minimum_review_rating' => '4',
        ])->assertRedirect();

        $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', 'application-settings')->latest('id')->firstOrFail();
        $this->assertTrue((bool) data_get($record->data, 'auto_approve_reviews'));
        $this->assertFalse((bool) data_get($record->data, 'require_review_approval'));
        $this->assertSame('4', (string) data_get($record->data, 'minimum_review_rating'));
    }

    public function test_review_settings_control_public_review_approval(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['email' => 'settings-review@example.test']);
        $product = Product::create([
            'name' => 'Settings Review Cap',
            'slug' => 'settings-review-cap',
            'sku' => 'SETTINGS-REVIEW-001',
            'description' => 'Review settings contract product.',
            'price' => 34.99,
            'stock' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.settings.save', 'application-settings'), [
            'auto_approve_reviews' => '1',
            'require_review_approval' => '1',
            'minimum_review_rating' => '4',
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('reviews.store', $product), [
            'rating' => 5,
            'title' => 'Automatically approved',
            'body' => 'This setting should publish this review.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'status' => 'approved',
        ]);
    }
}
