<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageManagerApprovalActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_manager_exposes_edit_and_public_approval_actions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Image Approval Hat',
            'slug' => 'image-approval-hat',
            'sku' => 'IMG-APP-001',
            'price' => 79,
            'stock' => 10,
            'status' => 'published',
            'is_active' => true,
        ]);
        $image = ProductMedia::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'product-media/test/image.png',
            'alt_text' => 'Test image',
            'sort_order' => 1,
            'active' => true,
            'approval_status' => 'pending',
            'mime_type' => 'image/png',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.images.index', [
                'product_id' => $product->id,
                'selected_media_id' => $image->id,
            ]));

        $response
            ->assertOk()
            ->assertSee('Approve selected for public')
            ->assertSee('Approve for public')
            ->assertSee('Edit Selected Image')
            ->assertSee('Save Image Details')
            ->assertSee('#im-edit-panel', false)
            ->assertSee(route('admin.media.approve', $image), false);
    }

    public function test_bulk_image_approval_publishes_selected_media(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Bulk Approval Hat',
            'slug' => 'bulk-approval-hat',
            'sku' => 'IMG-APP-002',
            'price' => 79,
            'stock' => 10,
            'status' => 'published',
            'is_active' => true,
        ]);

        $images = collect(range(1, 2))->map(fn (int $index) => ProductMedia::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'product-media/test/image-'.$index.'.png',
            'sort_order' => $index,
            'active' => true,
            'approval_status' => 'pending',
            'mime_type' => 'image/png',
        ]));

        $this->actingAs($admin)
            ->post(route('admin.images.bulk'), [
                'action' => 'approve',
                'media_ids' => $images->pluck('id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($images as $image) {
            $image->refresh();
            $this->assertSame('approved', $image->approval_status);
            $this->assertTrue((bool) $image->active);
            $this->assertNotNull($image->approved_at);
            $this->assertSame($admin->id, $image->approved_by);
        }
    }
}
