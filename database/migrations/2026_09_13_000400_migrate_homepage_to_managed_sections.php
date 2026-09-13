<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_pages') || ! Schema::hasTable('page_sections')) {
            return;
        }

        $homepage = DB::table('content_pages')
            ->where('slug', 'home')
            ->where('route_path', '/')
            ->where('page_kind', 'home')
            ->first(['id']);

        if (! $homepage || DB::table('page_sections')->where('content_page_id', $homepage->id)->exists()) {
            return;
        }

        $now = now();
        foreach ($this->sections() as $index => $section) {
            DB::table('page_sections')->insert([
                'uuid' => (string) Str::uuid(),
                'content_page_id' => $homepage->id,
                'type' => $section['type'],
                'label' => $section['label'],
                'sort_order' => $index,
                'region' => 'main',
                'locale' => 'en',
                'media_uuid' => null,
                'focal_point' => null,
                'devices' => json_encode(['desktop', 'tablet', 'mobile'], JSON_THROW_ON_ERROR),
                'variant' => $section['variant'] ?? null,
                'animation' => $section['animation'] ?? 'none',
                'analytics_key' => $section['analytics_key'],
                'validation_errors' => null,
                'settings' => json_encode($section['settings'], JSON_THROW_ON_ERROR),
                'visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Homepage sections are now user-editable content. A rollback must not
        // delete editorial changes or remove the public homepage composition.
    }

    /** @return list<array{type: string, label: string, analytics_key: string, settings: array<string, mixed>}> */
    private function sections(): array
    {
        return [
            [
                'type' => 'hero',
                'label' => 'Crafted in Limerick. Worn everywhere.',
                'variant' => 'campaign',
                'animation' => 'fade',
                'analytics_key' => 'homepage.hero',
                'settings' => [
                    'eyebrow' => 'CRAFTED IN LIMERICK.',
                    'title' => 'CRAFTED IN LIMERICK. WORN EVERYWHERE.',
                    'content' => 'VIRTUAL TRY-ON lets you preview Emerald Rozalia hats on your photo.',
                    'primary_label' => 'START VIRTUAL TRY-ON',
                    'primary_href' => '/virtual-tryon',
                    'secondary_label' => 'SHOP NEW ARRIVALS',
                    'secondary_href' => '/new-arrivals',
                    'tertiary_label' => 'OUR MANUFACTURING STORY',
                    'tertiary_href' => '/factory',
                    'alt' => 'Crafted in Limerick. Worn everywhere. Emerald Rozalia virtual try-on campaign.',
                ],
            ],
            [
                'type' => 'banners',
                'label' => 'Homepage campaign banners',
                'analytics_key' => 'homepage.banners',
                'settings' => [
                    'position' => 'Home - Main Slider',
                ],
            ],
            [
                'type' => 'benefits',
                'label' => 'Emerald Rozalia benefits',
                'analytics_key' => 'homepage.benefits',
                'settings' => [
                    'items' => [
                        ['icon' => 'clover', 'title' => 'MADE IN LIMERICK', 'content' => 'Proudly designing & manufacturing in Ireland.'],
                        ['icon' => 'star', 'title' => 'PREMIUM QUALITY', 'content' => 'Built to last with the finest materials.'],
                        ['icon' => 'users', 'title' => 'TRADE & BULK ORDERS WELCOME', 'content' => 'Solutions for businesses of all sizes.'],
                        ['icon' => 'truck', 'title' => 'FAST DISPATCH WORLDWIDE', 'content' => 'Reliable delivery across the globe.'],
                        ['icon' => 'globe', 'title' => 'GLOBAL REACH', 'content' => 'Irish roots. Worn everywhere.'],
                    ],
                ],
            ],
            [
                'type' => 'collections',
                'label' => 'Shop by collections',
                'analytics_key' => 'homepage.collections',
                'settings' => [
                    'title' => 'SHOP BY COLLECTIONS',
                    'items' => [
                        ['slug' => 'baseball-caps', 'title' => 'BASEBALL CAPS', 'copy' => 'Classic. Everyday. Made to perform.'],
                        ['slug' => 'bucket-hats', 'title' => 'BUCKET HATS', 'copy' => 'Comfortable. Versatile. Timeless.'],
                        ['slug' => 'snapbacks', 'title' => 'SNAPBACKS', 'copy' => 'Modern fit. Stand out.'],
                        ['slug' => 'irish-traditional-flat-caps', 'title' => 'IRISH TRADITIONAL FLAT CAPS', 'copy' => 'Authentic style. Irish tradition.', 'new' => true],
                        ['slug' => 'irish-heritage-hats', 'title' => 'IRISH HERITAGE HATS', 'copy' => 'Heritage designs. Timeless elegance.'],
                        ['slug' => 'beanies-more', 'title' => 'BEANIES & MORE', 'copy' => 'Warm. Stylish. Essential.'],
                    ],
                ],
            ],
            [
                'type' => 'heritage',
                'label' => 'The Irish Heritage Collection',
                'analytics_key' => 'homepage.heritage',
                'settings' => [
                    'eyebrow' => 'THE IRISH HERITAGE COLLECTION',
                    'title' => 'Tradition, Made in Limerick.',
                    'content' => 'Inspired by generations of Irish craftsmanship. Our flat caps and heritage hats are woven from premium fabrics and made to last.',
                    'button_label' => 'EXPLORE HERITAGE COLLECTION',
                    'button_href' => '/irish-heritage',
                    'badges' => [
                        ['icon' => 'clover', 'title' => 'AUTHENTIC IRISH STYLE'],
                        ['icon' => 'package', 'title' => 'PREMIUM TWEED & WOOL'],
                        ['icon' => 'settings', 'title' => 'EXPERT CRAFTSMANSHIP'],
                        ['icon' => 'home', 'title' => 'MADE IN LIMERICK'],
                    ],
                ],
            ],
            [
                'type' => 'products',
                'label' => 'Bestsellers',
                'analytics_key' => 'homepage.bestsellers',
                'settings' => [
                    'title' => 'BESTSELLERS',
                    'view_all_label' => 'VIEW ALL',
                    'view_all_href' => '/shop',
                    'source' => 'new_products',
                    'limit' => 6,
                ],
            ],
            [
                'type' => 'quality',
                'label' => 'Quality in every stitch',
                'analytics_key' => 'homepage.quality',
                'settings' => [
                    'eyebrow' => 'FROM CONCEPT TO CREATION.',
                    'title' => 'QUALITY IN EVERY STITCH.',
                    'content' => 'Every hat and cap is made in-house by our skilled team in Limerick. From design and pattern engineering to embroidery, finishing and quality inspection.',
                    'button_label' => 'SEE OUR PROCESS',
                    'button_href' => '/factory',
                ],
            ],
            [
                'type' => 'franchise',
                'label' => 'Franchise open now',
                'analytics_key' => 'homepage.franchise',
                'settings' => [
                    'eyebrow' => 'FRANCHISE OPEN NOW',
                    'title' => 'FOR IRELAND',
                    'content' => "Be part of Emerald Rozalia's growth journey. Own an exclusive territory and build a legacy with an Irish brand.",
                    'button_label' => 'APPLY FOR FRANCHISE',
                    'button_href' => '/franchise',
                ],
            ],
        ];
    }
};
