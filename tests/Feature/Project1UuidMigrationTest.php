<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Project1UuidMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_domain_schema_has_non_null_unique_public_identifiers(): void
    {
        foreach ([
            'users',
            'categories',
            'products',
            'orders',
            'order_items',
            'inquiries',
            'stores',
            'product_variants',
            'addresses',
            'wishlists',
            'reviews',
            'returns',
            'reward_transactions',
            'inventory_movements',
            'payment_transactions',
            'shipping_methods',
            'discounts',
            'admin_records',
        ] as $table) {
            $this->assertUuidColumnIsRequiredAndUnique($table, 'public_uuid');
        }

        $this->assertUuidColumnIsRequiredAndUnique('content_pages', 'uuid');
    }

    public function test_repair_migration_preserves_existing_uuids_repairs_duplicates_and_is_idempotent(): void
    {
        $products = collect(['A', 'B', 'C'])->map(fn (string $suffix): Product => Product::create([
            'name' => 'UUID Recovery '.$suffix,
            'slug' => 'uuid-recovery-'.strtolower($suffix),
            'sku' => 'UUID-RECOVERY-'.$suffix,
            'price' => 20,
            'stock' => 1,
            'is_active' => true,
        ]));

        Schema::table('products', static function (Blueprint $table): void {
            $table->dropColumn('public_uuid');
        });
        Schema::table('products', static function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable();
        });

        $preservedUuid = (string) Str::uuid();
        DB::table('products')->where('id', $products[0]->id)->update(['public_uuid' => $preservedUuid]);
        DB::table('products')->where('id', $products[1]->id)->update(['public_uuid' => $preservedUuid]);

        $migration = require base_path('database/migrations/2026_09_12_000300_repair_project1_uuid_contract.php');
        $migration->up();

        $recovered = DB::table('products')
            ->whereIn('id', $products->pluck('id'))
            ->orderBy('id')
            ->pluck('public_uuid');

        $this->assertSame($preservedUuid, $recovered[0]);
        $this->assertNotSame($preservedUuid, $recovered[1]);
        $this->assertNotNull($recovered[2]);
        $this->assertCount(3, $recovered->unique());

        $migration->up();

        $this->assertSame($recovered->all(), DB::table('products')
            ->whereIn('id', $products->pluck('id'))
            ->orderBy('id')
            ->pluck('public_uuid')
            ->all());
        $this->assertUuidColumnIsRequiredAndUnique('products', 'public_uuid');
    }

    private function assertUuidColumnIsRequiredAndUnique(string $table, string $column): void
    {
        $this->assertTrue(Schema::hasColumn($table, $column));

        $definition = collect(Schema::getColumns($table))->firstWhere('name', $column);
        $this->assertNotNull($definition);
        $this->assertFalse((bool) $definition['nullable']);

        $this->assertTrue(collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => (bool) ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === [$column]
        ));
    }
}
