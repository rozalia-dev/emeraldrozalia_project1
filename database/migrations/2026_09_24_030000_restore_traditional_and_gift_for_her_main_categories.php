<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Production catalogue repair. Isolated feature tests should not inherit
        // business catalogue rows from a data migration.
        if (app()->environment('testing') || ! Schema::hasTable('categories')) {
            return;
        }

        $companyId = null;
        if (Schema::hasTable('companies')) {
            $companyId = DB::table('companies')->where('code', 'ERL')->value('id')
                ?? DB::table('companies')->where('active', true)->orderBy('id')->value('id');
        }

        $now = now();

        $baseValues = function (string $name, string $taxonomy, int $sortOrder, string $icon) use ($companyId, $now): array {
            $values = [
                'parent_id' => null,
                'name' => $name,
                'description' => $name.' headwear catalogue for Emerald Rozalia.',
                'is_active' => true,
                'sort_order' => $sortOrder,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('categories', 'company_id')) {
                $values['company_id'] = $companyId;
            }
            if (Schema::hasColumn('categories', 'status')) {
                $values['status'] = 'active';
            }
            if (Schema::hasColumn('categories', 'is_visible')) {
                $values['is_visible'] = true;
            }
            if (Schema::hasColumn('categories', 'taxonomy_type')) {
                $values['taxonomy_type'] = $taxonomy;
            }
            if (Schema::hasColumn('categories', 'product_type')) {
                $values['product_type'] = null;
            }
            if (Schema::hasColumn('categories', 'icon')) {
                $values['icon'] = $icon;
            }

            return $values;
        };

        // Traditional must always exist as a visible Level-1 category.
        $traditional = DB::table('categories')->where('slug', 'traditional')->first();
        if (! $traditional) {
            $traditional = DB::table('categories')
                ->whereNull('parent_id')
                ->whereRaw('LOWER(name) = ?', ['traditional'])
                ->first();
        }

        $traditionalValues = $baseValues('Traditional', 'traditional', 1, 'hat');
        if ($traditional) {
            $updates = $traditionalValues;
            if ($traditional->slug !== 'traditional'
                && ! DB::table('categories')->where('slug', 'traditional')->where('id', '<>', $traditional->id)->exists()) {
                $updates['slug'] = 'traditional';
            }
            DB::table('categories')->where('id', $traditional->id)->update($updates);
        } else {
            $insert = $traditionalValues + [
                'slug' => 'traditional',
                'created_at' => $now,
            ];
            if (Schema::hasColumn('categories', 'public_uuid')) {
                $insert['public_uuid'] = (string) Str::uuid();
            }
            DB::table('categories')->insert($insert);
        }

        // "Gift for Her" is a main catalogue category. Keep the stable /gift
        // slug so existing public links continue to work.
        $gift = DB::table('categories')->where('slug', 'gift')->first();
        $giftForHer = DB::table('categories')->where('slug', 'gift-for-her')->first();

        if (! $gift && $giftForHer) {
            $gift = $giftForHer;
        }

        if ($gift && $giftForHer && $gift->id !== $giftForHer->id) {
            if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id')) {
                DB::table('products')->where('category_id', $giftForHer->id)->update(['category_id' => $gift->id]);
            }
            DB::table('categories')->where('parent_id', $giftForHer->id)->update(['parent_id' => $gift->id]);
            DB::table('categories')->where('id', $giftForHer->id)->delete();
        }

        $giftValues = $baseValues('Gift for Her', 'gift', 7, 'gift');
        if ($gift) {
            $giftValues['slug'] = 'gift';
            DB::table('categories')->where('id', $gift->id)->update($giftValues);
        } else {
            $insert = $giftValues + [
                'slug' => 'gift',
                'created_at' => $now,
            ];
            if (Schema::hasColumn('categories', 'public_uuid')) {
                $insert['public_uuid'] = (string) Str::uuid();
            }
            DB::table('categories')->insert($insert);
        }
    }

    public function down(): void
    {
        // No-op: these are canonical business catalogue rows and may contain
        // products or administrator edits after deployment.
    }
};
