<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'parent_id')) {
            return;
        }

        $traditional = $this->findParent('traditional', 'irish-traditional-flat-caps', 'Traditional');
        $heritage = $this->findParent('heritage', 'irish-heritage-hats', 'Heritage');

        if ($traditional) {
            $this->seedChildren($traditional, 'traditional', [
                ['Irish Flat Caps', 'irish-flat-caps'],
                ['Newsboy Caps', 'newsboy-caps'],
                ['Baker Boy Caps', 'baker-boy-caps'],
                ['8-Panel Caps', '8-panel-caps'],
                ['Driving Caps', 'driving-caps'],
                ['Donegal Tweed Caps', 'donegal-tweed-caps'],
                ['Herringbone Tweed Caps', 'herringbone-tweed-caps'],
                ['Patchwork Tweed Caps', 'patchwork-tweed-caps'],
                ['Wool Flat Caps', 'wool-flat-caps'],
                ['Linen Flat Caps', 'linen-flat-caps'],
                ['Waxed Cotton Flat Caps', 'waxed-cotton-flat-caps'],
                ['Country Caps', 'country-caps'],
                ['Traditional Work Caps', 'traditional-work-caps'],
                ['Premium Irish Tweed Caps', 'premium-irish-tweed-caps'],
            ]);
        }

        if ($heritage) {
            $this->seedChildren($heritage, 'heritage', [
                ['Irish Heritage Flat Caps', 'irish-heritage-flat-caps'],
                ['Heritage Baseball Caps', 'heritage-baseball-caps'],
                ['Heritage Bucket Hats', 'heritage-bucket-hats'],
                ['Heritage Beanies', 'heritage-beanies'],
                ['Vintage Heritage Caps', 'vintage-heritage-caps'],
                ['Celtic Collection', 'celtic-collection'],
                ['Gaelic Collection', 'gaelic-collection'],
                ['Shamrock Collection', 'shamrock-collection'],
                ['Claddagh Collection', 'claddagh-collection'],
                ['Irish Harp Collection', 'irish-harp-collection'],
                ['County Heritage Collection', 'county-heritage-collection'],
                ['Limerick Heritage Collection', 'limerick-heritage-collection'],
                ['Donegal Heritage Collection', 'donegal-heritage-collection'],
                ['Heritage Tweed Collection', 'heritage-tweed-collection'],
                ['Limited Heritage Editions', 'limited-heritage-editions'],
            ]);
        }
    }

    public function down(): void
    {
        // Intentional no-op. These are managed catalogue records and may be edited,
        // populated with products, or further nested after deployment. A rollback
        // must not remove business data created from them.
    }

    private function findParent(string $taxonomyType, string $slug, string $displayName): ?object
    {
        $base = DB::table('categories')->whereNull('parent_id');

        if (Schema::hasColumn('categories', 'taxonomy_type')) {
            $parent = (clone $base)->where('taxonomy_type', $taxonomyType)->orderBy('id')->first();
            if ($parent) {
                return $parent;
            }
        }

        $parent = (clone $base)->where('slug', $slug)->first();
        if ($parent) {
            return $parent;
        }

        return (clone $base)->where('name', $displayName)->first();
    }

    /**
     * @param array<int, array{0:string,1:string}> $children
     */
    private function seedChildren(object $parent, string $taxonomyType, array $children): void
    {
        foreach ($children as $index => [$name, $slug]) {
            $existing = DB::table('categories')->where('slug', $slug)->first();

            $values = [
                'parent_id' => $parent->id,
                'name' => $name,
                'description' => $taxonomyType === 'traditional'
                    ? 'Traditional Emerald Rozalia headwear inspired by Irish craftsmanship and classic cap making.'
                    : 'Emerald Rozalia heritage headwear celebrating Irish identity, regional tradition and cultural design.',
                'is_active' => true,
                'sort_order' => $index + 1,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('categories', 'company_id')) {
                $values['company_id'] = $parent->company_id ?? null;
            }
            if (Schema::hasColumn('categories', 'status')) {
                $values['status'] = 'active';
            }
            if (Schema::hasColumn('categories', 'is_visible')) {
                $values['is_visible'] = true;
            }
            if (Schema::hasColumn('categories', 'taxonomy_type')) {
                $values['taxonomy_type'] = $taxonomyType;
            }

            if ($existing) {
                $existingParentId = $existing->parent_id ?? null;
                if ($existingParentId === null || (int) $existingParentId === (int) $parent->id) {
                    DB::table('categories')->where('id', $existing->id)->update($values);
                }
                continue;
            }

            $insert = $values + [
                'slug' => $slug,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('categories', 'public_uuid')) {
                $insert['public_uuid'] = (string) Str::uuid();
            }

            DB::table('categories')->insert($insert);
        }
    }
};
