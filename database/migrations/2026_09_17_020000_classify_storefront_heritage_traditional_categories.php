<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'taxonomy_type')) {
            return;
        }

        foreach ([
            'irish-traditional-flat-caps' => 'traditional',
            'irish-heritage-hats' => 'heritage',
        ] as $slug => $taxonomyType) {
            DB::table('categories')
                ->where('slug', $slug)
                ->whereNull('taxonomy_type')
                ->update(['taxonomy_type' => $taxonomyType]);
        }
    }

    public function down(): void
    {
        // Intentional no-op: this is a data classification repair. Rolling back a
        // release must not erase taxonomy metadata that may subsequently be edited
        // through the unified category/taxonomy management flow.
    }
};
