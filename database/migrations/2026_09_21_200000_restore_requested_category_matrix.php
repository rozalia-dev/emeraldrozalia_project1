<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Production catalogue repair. Feature tests build isolated fixtures and
        // should not inherit business catalogue data from this migration.
        if (app()->environment('testing')) {
            return;
        }

        if (! Schema::hasTable('categories')) {
            return;
        }

        $companyId = Schema::hasTable('companies')
            ? DB::table('companies')->where('code', 'ERL')->value('id')
            : null;

        $definitions = [
            'traditional' => 'Traditional',
            'heritage' => 'Heritage',
            'gaa' => 'GAA',
            'english' => 'English',
            'uefa' => 'UEFA',
            'fifa' => 'FIFA',
            'gift' => 'Gift',
            'accessory' => 'Accessory',
            'customised' => 'Customised',
            'corporate' => 'Corporate',
        ];

        $productTypes = [
            'caps' => 'Caps',
            'hats' => 'Hats',
            'beanies' => 'Beanie',
        ];

        $now = now();

        foreach ($definitions as $sort => $label) {
            $taxonomy = $sort;
            $sortOrder = array_search($taxonomy, array_keys($definitions), true) + 1;
            $parent = DB::table('categories')->where('slug', $taxonomy)->first();

            $parentValues = [
                'parent_id' => null,
                'name' => $label,
                'description' => $label.' headwear catalogue for Emerald Rozalia.',
                'is_active' => true,
                'sort_order' => $sortOrder,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('categories', 'company_id')) {
                $parentValues['company_id'] = $companyId;
            }
            if (Schema::hasColumn('categories', 'status')) {
                $parentValues['status'] = 'active';
            }
            if (Schema::hasColumn('categories', 'is_visible')) {
                $parentValues['is_visible'] = true;
            }
            if (Schema::hasColumn('categories', 'taxonomy_type')) {
                $parentValues['taxonomy_type'] = $taxonomy;
            }
            if (Schema::hasColumn('categories', 'product_type')) {
                $parentValues['product_type'] = null;
            }

            if ($parent) {
                DB::table('categories')->where('id', $parent->id)->update($parentValues);
                $parentId = $parent->id;
            } else {
                $insert = $parentValues + [
                    'slug' => $taxonomy,
                    'created_at' => $now,
                ];
                if (Schema::hasColumn('categories', 'public_uuid')) {
                    $insert['public_uuid'] = (string) Str::uuid();
                }
                $parentId = DB::table('categories')->insertGetId($insert);
            }

            foreach ($productTypes as $typeIndex => $typeLabel) {
                $typeSlug = $taxonomy.'-'.$typeIndex;
                $child = DB::table('categories')->where('slug', $typeSlug)->first();
                $childValues = [
                    'parent_id' => $parentId,
                    'name' => $typeLabel,
                    'description' => $typeLabel.' under '.$label.'.',
                    'is_active' => true,
                    'sort_order' => array_search($typeIndex, array_keys($productTypes), true) + 1,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('categories', 'company_id')) {
                    $childValues['company_id'] = $companyId;
                }
                if (Schema::hasColumn('categories', 'status')) {
                    $childValues['status'] = 'active';
                }
                if (Schema::hasColumn('categories', 'is_visible')) {
                    $childValues['is_visible'] = true;
                }
                if (Schema::hasColumn('categories', 'taxonomy_type')) {
                    $childValues['taxonomy_type'] = $taxonomy;
                }
                if (Schema::hasColumn('categories', 'product_type')) {
                    $childValues['product_type'] = $typeIndex;
                }

                if ($child) {
                    DB::table('categories')->where('id', $child->id)->update($childValues);
                    continue;
                }

                $insert = $childValues + [
                    'slug' => $typeSlug,
                    'created_at' => $now,
                ];
                if (Schema::hasColumn('categories', 'public_uuid')) {
                    $insert['public_uuid'] = (string) Str::uuid();
                }
                DB::table('categories')->insert($insert);
            }
        }
    }

    public function down(): void
    {
        // Canonical catalogue records are business data. A release rollback must
        // not delete categories that may already contain products or manual edits.
    }
};
