<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const UUID_COLUMNS = [
        'users' => 'public_uuid',
        'categories' => 'public_uuid',
        'products' => 'public_uuid',
        'orders' => 'public_uuid',
        'order_items' => 'public_uuid',
        'inquiries' => 'public_uuid',
        'stores' => 'public_uuid',
        'product_variants' => 'public_uuid',
        'addresses' => 'public_uuid',
        'wishlists' => 'public_uuid',
        'reviews' => 'public_uuid',
        'returns' => 'public_uuid',
        'reward_transactions' => 'public_uuid',
        'inventory_movements' => 'public_uuid',
        'payment_transactions' => 'public_uuid',
        'shipping_methods' => 'public_uuid',
        'discounts' => 'public_uuid',
        'admin_records' => 'public_uuid',
        'content_pages' => 'uuid',
    ];

    public function up(): void
    {
        foreach (self::UUID_COLUMNS as $table => $column) {
            $this->ensureUuidColumn($table, $column);
        }
    }

    /**
     * UUIDs are public identifiers. Keeping them on rollback is safer than
     * deleting identifiers that may already be present in external links.
     */
    public function down(): void
    {
    }

    private function ensureUuidColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, static function (Blueprint $blueprint) use ($column): void {
                $blueprint->uuid($column)->nullable();
            });
        }

        $this->backfillMissingUuids($table, $column);
        $this->repairDuplicateUuids($table, $column);

        if (! $this->hasUniqueIndex($table, $column)) {
            $indexName = $this->uniqueIndexName($table, $column);
            Schema::table($table, static function (Blueprint $blueprint) use ($column, $indexName): void {
                $blueprint->unique($column, $indexName);
            });
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($column): void {
            $blueprint->uuid($column)->nullable(false)->change();
        });
    }

    private function backfillMissingUuids(string $table, string $column): void
    {
        DB::table($table)
            ->whereNull($column)
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->whereNull($column)
                        ->update([$column => (string) Str::uuid()]);
                }
            });
    }

    private function repairDuplicateUuids(string $table, string $column): void
    {
        $duplicateValues = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column);

        foreach ($duplicateValues as $duplicateValue) {
            $duplicateIds = DB::table($table)
                ->where($column, $duplicateValue)
                ->orderBy('id')
                ->pluck('id');

            foreach ($duplicateIds->skip(1) as $duplicateId) {
                DB::table($table)
                    ->where('id', $duplicateId)
                    ->update([$column => (string) Str::uuid()]);
            }
        }
    }

    private function hasUniqueIndex(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            $columns = $index['columns'] ?? [];
            if ((bool) ($index['unique'] ?? false) && $columns === [$column]) {
                return true;
            }
        }

        return false;
    }

    private function uniqueIndexName(string $table, string $column): string
    {
        $existingNames = [];
        foreach (Schema::getIndexes($table) as $index) {
            $existingNames[(string) ($index['name'] ?? '')] = true;
        }

        $baseName = $table.'_'.$column.'_unique';
        $candidate = $baseName;
        $suffix = 0;

        while (isset($existingNames[$candidate])) {
            $suffix++;
            $candidate = $baseName.'_hardened'.($suffix > 1 ? '_'.$suffix : '');
        }

        return $candidate;
    }
};
