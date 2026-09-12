<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TENANT_TABLES = [
        'products',
        'categories',
        'orders',
        'inquiries',
        'stores',
        'admin_records',
        'discounts',
        'shipping_methods',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('legal_name')->nullable();
                $table->string('code')->unique();
                $table->string('country_code', 2)->default('IE');
                $table->string('base_currency', 3)->default('EUR');
                $table->string('default_locale', 10)->default('en');
                $table->boolean('active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table): void {
                $table->string('code', 3)->primary();
                $table->string('name');
                $table->string('symbol', 8);
                $table->unsignedTinyInteger('decimals')->default(2);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table): void {
                $table->id();
                $table->string('base_currency', 3);
                $table->string('quote_currency', 3);
                $table->decimal('rate', 18, 8);
                $table->date('rate_date');
                $table->string('source')->default('manual');
                $table->timestamps();
                $table->unique(['base_currency', 'quote_currency', 'rate_date']);
            });
        }

        if (! Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $table): void {
                $table->string('locale', 10)->primary();
                $table->string('name');
                $table->string('native_name');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_user')) {
            Schema::create('company_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role')->default('staff');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->unique(['company_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('company_languages')) {
            Schema::create('company_languages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('locale', 10);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->unique(['company_id', 'locale']);
            });
        }

        if (! Schema::hasTable('company_currencies')) {
            Schema::create('company_currencies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('currency_code', 3);
                $table->boolean('is_base')->default(false);
                $table->boolean('enabled_storefront')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'currency_code']);
            });
        }

        foreach (self::TENANT_TABLES as $table) {
            $this->addCompanyColumnIfMissing($table);
        }

        $this->addColumnsIfMissing('orders', [
            'currency_code' => fn (Blueprint $table): mixed => $table->string('currency_code', 3)->default('EUR'),
            'exchange_rate' => fn (Blueprint $table): mixed => $table->decimal('exchange_rate', 18, 8)->default(1),
        ]);
        $this->addColumnsIfMissing('products', [
            'translations' => fn (Blueprint $table): mixed => $table->json('translations')->nullable(),
        ]);
        $this->addColumnsIfMissing('categories', [
            'translations' => fn (Blueprint $table): mixed => $table->json('translations')->nullable(),
        ]);
    }

    public function down(): void
    {
        // Base tables reference companies. Remove those constraints and columns
        // before dropping the company context tables so MySQL and PostgreSQL
        // follow the same dependency-safe rollback order.
        foreach (self::TENANT_TABLES as $table) {
            $this->dropColumnIfPresent($table, 'company_id');
        }

        $this->dropColumnIfPresent('orders', 'currency_code');
        $this->dropColumnIfPresent('orders', 'exchange_rate');
        $this->dropColumnIfPresent('products', 'translations');
        $this->dropColumnIfPresent('categories', 'translations');

        foreach (['company_currencies', 'company_languages', 'company_user', 'exchange_rates', 'languages', 'currencies', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function addCompanyColumnIfMissing(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();
        });
    }

    /**
     * @param array<string, callable(Blueprint): mixed> $columns
     */
    private function addColumnsIfMissing(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $missing = array_filter(
            $columns,
            fn (callable $definition, string $column): bool => ! Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($missing): void {
            foreach ($missing as $definition) {
                $definition($table);
            }
        });
    }

    private function dropColumnIfPresent(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (! in_array($column, $foreignKey['columns'] ?? [], true)) {
                continue;
            }

            $name = $foreignKey['name'] ?? null;

            if ($name !== null) {
                Schema::table($table, function (Blueprint $table) use ($name): void {
                    $table->dropForeign($name);
                });
            }
        }

        Schema::table($table, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }
};
