<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private const ADDED_TABLES = [
        'addresses',
        'product_variants',
        'product_media',
        'variant_media',
        'product_spins',
        'spin_visits',
        'try_on_assets',
        'try_on_visits',
        'video_plays',
        'order_items',
        'inventory_movements',
        'wishlists',
        'reviews',
        'returns',
        'reward_transactions',
        'customer_groups',
        'customer_segments',
        'customer_profiles',
        'content_pages',
        'page_sections',
        'page_revisions',
        'banner_revisions',
        'media_asset_versions',
        'conversation_messages',
        'integration_connections',
        'audit_logs',
        'automation_rules',
        'backup_runs',
    ];

    /** @var array<int, string> */
    private const EXISTING_TENANT_TABLES = [
        'products',
        'categories',
        'orders',
        'inquiries',
        'stores',
        'admin_records',
        'discounts',
        'shipping_methods',
        'payment_transactions',
        'media_assets',
        'banners',
        'franchise_applications',
        'franchise_stores',
        'franchise_milestones',
        'conversations',
        'communication_templates',
        'approvals',
        'product_collections',
        'seo_settings',
        'seo_audits',
        'seo_issues',
        'seo_redirects',
        'theme_versions',
    ];

    public function up(): void
    {
        foreach (self::ADDED_TABLES as $tableName) {
            $this->addCompanyColumn($tableName);
        }

        $defaultCompanyId = $this->defaultCompanyId();
        if ($defaultCompanyId === null) {
            return;
        }

        foreach (self::EXISTING_TENANT_TABLES as $tableName) {
            $this->fillMissingCompanyIds($tableName, static fn (object $row): int => $defaultCompanyId);
        }

        $this->fillMissingCompanyIds('content_pages', static fn (object $row): int => $defaultCompanyId);

        $this->fillFromParent('product_variants', 'product_id', 'products', $defaultCompanyId);
        $this->fillFromParent('product_media', 'product_id', 'products', $defaultCompanyId);
        $this->fillFromParent('product_spins', 'product_id', 'products', $defaultCompanyId);
        $this->fillFromParent('try_on_assets', 'product_id', 'products', $defaultCompanyId);
        $this->fillFromParent('inventory_movements', 'product_id', 'products', $defaultCompanyId);
        $this->fillFromParent('wishlists', 'product_id', 'products', $defaultCompanyId);
        $this->fillFromParent('reviews', 'product_id', 'products', $defaultCompanyId);
        $this->fillFromParent('order_items', 'order_id', 'orders', $defaultCompanyId);
        $this->fillFromParent('returns', 'order_id', 'orders', $defaultCompanyId);
        $this->fillFromParent('variant_media', 'product_variant_id', 'product_variants', $defaultCompanyId);
        $this->fillFromParent('spin_visits', 'product_spin_id', 'product_spins', $defaultCompanyId);
        $this->fillFromParent('try_on_visits', 'try_on_asset_id', 'try_on_assets', $defaultCompanyId);
        $this->fillFromParent('video_plays', 'product_media_id', 'product_media', $defaultCompanyId);
        $this->fillFromParent('page_sections', 'content_page_id', 'content_pages', $defaultCompanyId);
        $this->fillFromParent('page_revisions', 'content_page_id', 'content_pages', $defaultCompanyId);
        $this->fillFromParent('banner_revisions', 'banner_id', 'banners', $defaultCompanyId);
        $this->fillFromParent('media_asset_versions', 'media_asset_id', 'media_assets', $defaultCompanyId);
        $this->fillFromParent('franchise_milestones', 'franchise_application_id', 'franchise_applications', $defaultCompanyId);
        $this->fillFromParent('conversation_messages', 'conversation_id', 'conversations', $defaultCompanyId);

        foreach (['addresses', 'customer_profiles', 'reward_transactions'] as $tableName) {
            $this->fillFromUserMembership($tableName, $defaultCompanyId);
        }

        foreach (['customer_groups', 'customer_segments', 'integration_connections', 'audit_logs', 'automation_rules', 'backup_runs'] as $tableName) {
            $this->fillMissingCompanyIds($tableName, static fn (object $row): int => $defaultCompanyId);
        }
    }

    public function down(): void
    {
        foreach (self::ADDED_TABLES as $tableName) {
            $this->dropCompanyColumn($tableName);
        }
    }

    private function addCompanyColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'company_id')) {
            return;
        }

        Schema::table($tableName, static function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
        });
    }

    private function defaultCompanyId(): ?int
    {
        $id = DB::table('companies')->where('active', true)->orderBy('id')->value('id')
            ?? DB::table('companies')->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @param Closure(object): int $resolver */
    private function fillMissingCompanyIds(string $tableName, Closure $resolver): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
            return;
        }

        DB::table($tableName)
            ->whereNull('company_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($tableName, $resolver): void {
                foreach ($rows as $row) {
                    DB::table($tableName)
                        ->where('id', $row->id)
                        ->whereNull('company_id')
                        ->update(['company_id' => $resolver($row)]);
                }
            });
    }

    private function fillFromParent(string $tableName, string $foreignKey, string $parentTable, int $defaultCompanyId): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id') || ! Schema::hasColumn($tableName, $foreignKey)) {
            return;
        }

        $this->fillMissingCompanyIds($tableName, function (object $row) use ($foreignKey, $parentTable, $defaultCompanyId): int {
            $companyId = DB::table($parentTable)->where('id', $row->{$foreignKey})->value('company_id');
            return $companyId === null ? $defaultCompanyId : (int) $companyId;
        });
    }

    private function fillFromUserMembership(string $tableName, int $defaultCompanyId): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id') || ! Schema::hasColumn($tableName, 'user_id')) {
            return;
        }

        $this->fillMissingCompanyIds($tableName, function (object $row) use ($defaultCompanyId): int {
            $companyId = DB::table('company_user')
                ->where('user_id', $row->user_id)
                ->orderByDesc('is_default')
                ->orderBy('company_id')
                ->value('company_id');

            return $companyId === null ? $defaultCompanyId : (int) $companyId;
        });
    }

    private function dropCompanyColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
            return;
        }

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (! in_array('company_id', $foreignKey['columns'] ?? [], true)) {
                continue;
            }

            $name = $foreignKey['name'] ?? null;
            if ($name !== null) {
                Schema::table($tableName, static function (Blueprint $table) use ($name): void {
                    $table->dropForeign($name);
                });
            }
        }

        Schema::table($tableName, static function (Blueprint $table): void {
            $table->dropColumn('company_id');
        });
    }
};
