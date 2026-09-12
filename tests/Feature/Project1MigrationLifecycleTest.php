<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Project1MigrationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_multicompany_context_has_a_foreign_key_on_each_base_domain_table(): void
    {
        foreach ([
            'products',
            'categories',
            'orders',
            'inquiries',
            'stores',
            'admin_records',
            'discounts',
            'shipping_methods',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'company_id'));
            $this->assertTrue(collect(Schema::getForeignKeys($table))->contains(
                fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === ['company_id']
                    && ($foreignKey['foreign_table'] ?? null) === 'companies'
                    && ($foreignKey['foreign_columns'] ?? []) === ['id']
            ), "{$table}.company_id must reference companies.id");
        }
    }

    public function test_multicompany_migration_can_be_replayed_after_the_schema_is_recorded(): void
    {
        $migration = require base_path('database/migrations/2026_09_01_000300_create_multicompany_localization_tables.php');

        $migration->up();

        $this->assertTrue(Schema::hasTable('companies'));
        $this->assertTrue(Schema::hasTable('company_user'));
        $this->assertTrue(Schema::hasColumn('orders', 'currency_code'));
        $this->assertTrue(Schema::hasColumn('orders', 'exchange_rate'));
        $this->assertTrue(Schema::hasColumn('products', 'translations'));
        $this->assertTrue(Schema::hasColumn('categories', 'translations'));
    }
}
