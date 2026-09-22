<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'icon')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->string('icon', 40)->nullable();
            });
        }

        $defaults = [
            'traditional' => 'hat',
            'heritage' => 'clover',
            'gaa' => 'football',
            'english' => 'football',
            'premier' => 'football',
            'uefa' => 'football',
            'fifa' => 'globe',
            'gift' => 'gift',
            'accessor' => 'tag',
            'custom' => 'palette',
            'corporate' => 'briefcase',
        ];

        foreach ($defaults as $needle => $icon) {
            DB::table('categories')
                ->whereNull('parent_id')
                ->where(function ($query) use ($needle): void {
                    $query->whereRaw('LOWER(slug) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%']);
                })
                ->where(function ($query): void {
                    $query->whereNull('icon')->orWhere('icon', '');
                })
                ->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'icon')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn('icon');
            });
        }
    }
};
