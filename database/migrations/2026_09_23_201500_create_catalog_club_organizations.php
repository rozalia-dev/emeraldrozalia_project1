<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_club_organizations')) {
            Schema::create('catalog_club_organizations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('catalog_club_id')
                    ->constrained('catalog_clubs')
                    ->cascadeOnDelete();
                $table->string('taxonomy_type', 24)->index();
                $table->timestamps();

                $table->unique(
                    ['catalog_club_id', 'taxonomy_type'],
                    'catalog_club_organization_unique'
                );
            });
        }

        if (! Schema::hasTable('catalog_clubs')) {
            return;
        }

        $now = now();
        $clubs = DB::table('catalog_clubs')
            ->select('id', 'catalog_country_id', 'governing_body')
            ->orderBy('id')
            ->get();

        $englandId = Schema::hasTable('catalog_countries')
            ? DB::table('catalog_countries')->where('code', 'ENG')->value('id')
            : null;

        foreach ($clubs as $club) {
            $organizations = [strtolower(trim((string) $club->governing_body))];

            if ($englandId && (int) $club->catalog_country_id === (int) $englandId) {
                if (in_array($club->governing_body, ['english', 'uefa'], true)) {
                    $organizations[] = 'english';
                    $organizations[] = 'uefa';
                }
            }

            foreach (array_values(array_unique(array_filter($organizations))) as $organization) {
                DB::table('catalog_club_organizations')->updateOrInsert(
                    [
                        'catalog_club_id' => $club->id,
                        'taxonomy_type' => $organization,
                    ],
                    [
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_club_organizations');
    }
};
