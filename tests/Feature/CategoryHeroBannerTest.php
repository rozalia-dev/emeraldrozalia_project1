<?php

namespace Tests\Feature;

use App\Models\{Banner, Category, Company, MediaAsset};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHeroBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_hero_banner_renders_only_on_its_selected_category(): void
    {
        $company = Company::create([
            'name' => 'Emerald Rozalia Test',
            'legal_name' => 'Emerald Rozalia Test Limited',
            'code' => 'ERTEST',
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);

        $traditional = Category::create([
            'company_id' => $company->id,
            'name' => 'Category Hero Traditional',
            'slug' => 'category-hero-traditional',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 901,
        ]);
        $heritage = Category::create([
            'company_id' => $company->id,
            'name' => 'Category Hero Heritage',
            'slug' => 'category-hero-heritage',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 902,
        ]);

        $media = MediaAsset::create([
            'company_id' => $company->id,
            'name' => 'Traditional category hero',
            'disk' => 'local',
            'path' => 'site-media/banners/traditional-hero.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 700,
            'alt_text' => 'Traditional Emerald Rozalia hats',
            'approval_status' => 'approved',
            'active' => true,
        ]);

        Banner::create([
            'company_id' => $company->id,
            'title' => 'Traditional Hero',
            'type' => 'banner',
            'position' => 'Category Hero',
            'target_url' => '/',
            'target_type' => 'internal',
            'status' => 'published',
            'priority' => 100,
            'media_uuid' => $media->uuid,
            'specific_pages' => ['category:category-hero-traditional'],
            'device_visibility' => ['desktop', 'tablet', 'mobile'],
            'alt_text' => 'Traditional Emerald Rozalia hats',
        ]);

        $mediaUrl = route('media.public', ['uuid' => $media->uuid]);

        $this->get(route('category', $traditional))
            ->assertOk()
            ->assertSee('has-category-banner', false)
            ->assertSee($mediaUrl, false)
            ->assertSee('Traditional Emerald Rozalia hats', false);

        $this->get(route('category', $heritage))
            ->assertOk()
            ->assertDontSee($mediaUrl, false);
    }

    public function test_banner_upload_source_is_auto_approved_for_public_delivery(): void
    {
        $media = MediaAsset::create([
            'name' => 'Admin banner upload',
            'disk' => 'local',
            'path' => 'site-media/banners/admin-upload.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 700,
            'approval_status' => 'pending',
            'active' => false,
            'metadata' => ['source' => 'banner-upload'],
        ]);

        $this->assertSame('approved', $media->approval_status);
        $this->assertTrue((bool) $media->active);
        $this->assertNotNull($media->approved_at);
    }
}
