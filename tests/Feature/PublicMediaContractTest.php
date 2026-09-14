<?php

namespace Tests\Feature;

use App\Models\{ContentPage, MediaAsset, MediaAssetVersion, Product, ProductCollection, ProductMedia, User};
use App\Services\PublicMediaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_product_media_is_available_through_the_public_uuid_route(): void
    {
        Storage::fake('public');
        $product = Product::create([
            'name' => 'Approved Media Cap',
            'slug' => 'approved-media-cap',
            'sku' => 'MEDIA-APPROVED-001',
            'price' => 39.99,
            'stock' => 4,
            'is_active' => true,
        ]);
        Storage::disk('public')->put('product-media/approved.webp', 'approved image');
        Storage::disk('public')->put('product-media/mobile.webp', 'mobile image');
        $approved = ProductMedia::create([
            'product_id' => $product->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'product-media/approved.webp',
            'mime_type' => 'image/webp',
            'width' => 1600,
            'height' => 1200,
            'responsive_variants' => ['mobile' => ['path' => 'product-media/mobile.webp', 'width' => 768, 'height' => 576]],
            'alt_text' => 'Approved Emerald Rozalia cap',
            'active' => true,
            'approval_status' => 'approved',
        ]);
        Storage::disk('public')->put('product-media/pending.webp', 'pending image');
        $pending = ProductMedia::create([
            'product_id' => $product->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'product-media/pending.webp',
            'active' => true,
            'approval_status' => 'pending',
        ]);

        $this->get(route('media.public', $approved->uuid))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('media.public', [$approved->uuid, 'mobile']))->assertOk();
        $this->get(route('media.public', $pending->uuid))->assertNotFound();

        $this->get(route('product', $product))
            ->assertOk()
            ->assertSee(route('media.public', $approved->uuid), false)
            ->assertSee('Approved Emerald Rozalia cap', false)
            ->assertDontSee('product-media/approved.webp', false);
    }

    public function test_generic_site_media_is_private_until_approved_and_is_available_to_the_builder_after_approval(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::disk('local')->put('site-media/hero.webp', 'hero image');
        $asset = MediaAsset::create([
            'name' => 'Homepage hero',
            'disk' => 'local',
            'path' => 'site-media/hero.webp',
            'mime_type' => 'image/webp',
            'alt_text' => 'Limerick workshop hero',
            'approval_status' => 'pending',
            'active' => true,
        ]);

        $this->get(route('media.public', $asset->uuid))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.pages.create'))->assertOk()->assertSee('data-builder-approved-media', false);

        $this->actingAs($admin)->post(route('admin.site-media.approve', $asset))->assertRedirect();
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'approval_status' => 'approved', 'active' => true]);
        $this->get(route('media.public', $asset->uuid))->assertOk();
        $this->actingAs($admin)->get(route('admin.pages.create'))
            ->assertOk()
            ->assertSee('Approved public media', false)
            ->assertSee('data-builder-approved-media', false);
    }

    public function test_admin_public_media_preview_resolves_the_uuid_without_exposing_a_storage_path(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::disk('local')->put('site-media/preview.webp', 'preview image');
        $asset = MediaAsset::create([
            'name' => 'Preview image',
            'disk' => 'local',
            'path' => 'site-media/preview.webp',
            'mime_type' => 'image/webp',
            'approval_status' => 'pending',
            'active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.site-media.preview', $asset->uuid))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename="preview.webp"')
            ->assertDontSee('site-media/preview.webp', false);
    }

    public function test_existing_non_home_pages_receive_a_starter_canvas_in_page_manager(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Editorial page',
            'slug' => 'editorial-page',
            'status' => 'draft',
            'locale' => 'en',
            'template' => 'standard',
            'navigation_visible' => false,
            'meta' => ['settings' => ['visibility' => 'public']],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('page-builder-block--server', false)
            ->assertSee('01 · Hero', false)
            ->assertSee('02 · Content', false);
    }

    public function test_media_manager_uploads_are_pending_and_page_sections_cannot_reference_unapproved_media(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.site-media.store'), [
            'file' => UploadedFile::fake()->image('workshop.jpg', 1200, 800),
            'name' => 'Workshop image',
            'alt_text' => 'Workshop image',
        ])->assertRedirect();
        $asset = MediaAsset::query()->where('name', 'Workshop image')->firstOrFail();
        $this->assertSame('pending', $asset->approval_status);

        $page = ContentPage::query()->where('slug', 'home')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.pages.update', $page), [
            'title' => $page->title,
            'slug' => 'home',
            'intro' => $page->intro,
            'body' => $page->body,
            'status' => 'published',
            'locale' => 'en',
            'template' => 'home',
            'navigation_visible' => '0',
            'indexable' => '1',
            'visibility' => 'public',
            'devices' => ['desktop', 'tablet', 'mobile'],
            'sections' => json_encode([[
                'type' => 'hero',
                'label' => 'Pending hero',
                'media_uuid' => $asset->uuid,
                'settings' => ['title' => 'Pending hero'],
                'visible' => true,
            ]]),
        ])->assertRedirect();

        $this->assertDatabaseHas('page_sections', ['content_page_id' => $page->id, 'media_uuid' => null]);
    }

    public function test_public_media_upload_rejects_images_outside_the_dimension_contract(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.site-media.store'), [
                'file' => UploadedFile::fake()->image('too-small.jpg', 32, 32),
                'name' => 'Too small',
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('media_assets', ['name' => 'Too small']);
    }

    public function test_baseline_public_artwork_is_registered_and_rendered_through_uuid_delivery(): void
    {
        $asset = MediaAsset::query()
            ->where('asset_key', 'legacy:assets/brand/contact-hero-reference.png')
            ->firstOrFail();

        $this->get(route('media.public', $asset->uuid))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee(route('media.public', $asset->uuid), false)
            ->assertDontSee('src="/assets/brand/contact-hero-reference.png', false)
            ->assertSee('id="public-media-contract"', false);
    }

    public function test_variant_media_must_be_approved_before_public_delivery(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Variant Approval Cap',
            'slug' => 'variant-approval-cap',
            'sku' => 'MEDIA-VARIANT-001',
            'price' => 35,
            'stock' => 2,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MEDIA-VARIANT-001-A',
            'stock' => 2,
            'stock_total' => 2,
            'status' => 'active',
            'is_active' => true,
        ]);
        Storage::disk('public')->put('variant-media/approval.webp', 'variant image');
        $media = $variant->media()->create([
            'type' => 'image',
            'disk' => 'public',
            'path' => 'variant-media/approval.webp',
            'mime_type' => 'image/webp',
            'approval_status' => 'pending',
            'active' => true,
        ]);

        $this->get(route('media.public', $media->uuid))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.variants.media.approve', [$variant, $media]))->assertRedirect();
        $this->get(route('media.public', $media->uuid))->assertOk();
        $this->actingAs($admin)->post(route('admin.variants.media.reject', [$variant, $media]))->assertRedirect();
        $this->get(route('media.public', $media->uuid))->assertNotFound();
    }

    public function test_site_media_replacement_archive_trash_and_version_restore_are_recoverable(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.site-media.store'), [
            'file' => UploadedFile::fake()->image('original.jpg', 1200, 800),
            'name' => 'Lifecycle image',
            'alt_text' => 'Original lifecycle image',
        ])->assertRedirect();
        $asset = MediaAsset::query()->where('name', 'Lifecycle image')->firstOrFail();
        $originalPath = $asset->path;
        $this->assertDatabaseHas('media_asset_versions', ['media_asset_id' => $asset->id, 'version' => 1]);

        $this->actingAs($admin)->patch(route('admin.site-media.update', $asset), [
            'name' => 'Lifecycle image',
            'alt_text' => 'Replacement lifecycle image',
            'replace_file' => UploadedFile::fake()->image('replacement.jpg', 1000, 700),
        ])->assertRedirect();
        $asset->refresh();
        $this->assertSame('pending', $asset->approval_status);
        $this->assertFalse($asset->active);
        $this->assertNotSame($originalPath, $asset->path);
        Storage::disk('local')->assertExists($originalPath);
        $this->assertDatabaseHas('media_asset_versions', ['media_asset_id' => $asset->id, 'version' => 2]);

        $this->actingAs($admin)->post(route('admin.site-media.approve', $asset))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.site-media.archive', $asset->uuid))->assertRedirect();
        $asset->refresh();
        $this->assertSame('archived', $asset->approval_status);
        $this->get(route('media.public', $asset->uuid))->assertNotFound();

        $this->actingAs($admin)->post(route('admin.site-media.restore', $asset->uuid))->assertRedirect();
        $asset->refresh();
        $this->assertSame('approved', $asset->approval_status);
        $this->assertTrue($asset->active);
        $this->get(route('media.public', $asset->uuid))->assertOk();

        $versionOne = MediaAssetVersion::query()->where('media_asset_id', $asset->id)->where('version', 1)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.site-media.version.restore', [
            'asset' => $asset->uuid,
            'version' => $versionOne->version,
        ]))->assertRedirect();
        $asset->refresh();
        $this->assertSame('pending', $asset->approval_status);
        $this->assertFalse($asset->active);
        $this->assertSame($versionOne->path, $asset->path);
        $this->assertDatabaseHas('media_asset_versions', ['media_asset_id' => $asset->id, 'version' => 3]);

        $this->actingAs($admin)->delete(route('admin.site-media.destroy', $asset))->assertRedirect();
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
        $this->actingAs($admin)->get(route('admin.site-media.trash'))->assertOk()->assertSee('Lifecycle image', false);
        $this->actingAs($admin)->post(route('admin.site-media.restore', $asset->uuid))->assertRedirect();
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'deleted_at' => null]);
    }

    public function test_permanent_site_media_deletion_is_blocked_while_referenced(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::disk('local')->put('site-media/referenced.webp', 'referenced image');
        $asset = MediaAsset::create([
            'name' => 'Referenced media',
            'disk' => 'local',
            'path' => 'site-media/referenced.webp',
            'mime_type' => 'image/webp',
            'approval_status' => 'approved',
            'active' => true,
        ]);
        ProductCollection::create([
            'name' => 'Referenced collection',
            'slug' => 'referenced-collection',
            'media_uuid' => $asset->uuid,
        ]);

        $this->actingAs($admin)->delete(route('admin.site-media.destroy', $asset))->assertRedirect();
        $response = $this->actingAs($admin)->delete(route('admin.site-media.permanent-destroy', $asset->uuid));
        $response->assertSessionHasErrors('media');
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    public function test_resolver_rejects_preloaded_unapproved_or_inactive_product_media(): void
    {
        $product = Product::create([
            'name' => 'Resolver Boundary Cap',
            'slug' => 'resolver-boundary-cap',
            'sku' => 'MEDIA-BOUNDARY-001',
            'price' => 35,
            'stock' => 2,
            'is_active' => true,
        ]);
        $pending = ProductMedia::create([
            'product_id' => $product->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'product-media/pending-boundary.webp',
            'mime_type' => 'image/webp',
            'active' => true,
            'approval_status' => 'pending',
        ]);

        $product->setRelation('media', collect([$pending]));

        $this->assertNull(app(PublicMediaResolver::class)->forProduct($product));

        $product->update(['is_active' => false]);
        $this->get(route('media.public', $pending->uuid))->assertNotFound();
    }

    public function test_public_product_and_try_on_payloads_do_not_emit_legacy_media_paths(): void
    {
        $product = Product::create([
            'name' => 'Legacy Payload Boundary Cap',
            'slug' => 'legacy-payload-boundary-cap',
            'sku' => 'MEDIA-BOUNDARY-002',
            'price' => 35,
            'stock' => 2,
            'is_active' => true,
            'spin_images' => ['/private/legacy-frame.jpg'],
            'try_on_asset' => '/private/legacy-overlay.png',
        ]);

        $this->get(route('product', $product))
            ->assertOk()
            ->assertDontSee('legacy-frame.jpg', false)
            ->assertDontSee('legacy-overlay.png', false);
        $this->get(route('virtual-tryon'))
            ->assertOk()
            ->assertDontSee('legacy-frame.jpg', false)
            ->assertDontSee('legacy-overlay.png', false);
    }
}
