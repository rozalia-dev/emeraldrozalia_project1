<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_media')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $this->replacePostgresTypeConstraint(['image','video','spin_360','try_on','document']);

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `product_media` MODIFY `type` ENUM('image','video','spin_360','try_on','document') NOT NULL");

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $this->rebuildSqliteTable(['image','video','spin_360','try_on','document']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_media')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::table('product_media')->where('type','document')->update(['type' => 'image']);
            $this->replacePostgresTypeConstraint(['image','video','spin_360','try_on']);

            return;
        }

        if ($driver === 'mysql') {
            DB::table('product_media')->where('type','document')->update(['type' => 'image']);
            DB::statement("ALTER TABLE `product_media` MODIFY `type` ENUM('image','video','spin_360','try_on') NOT NULL");

            return;
        }

        if ($driver === 'sqlite') {
            DB::table('product_media')->where('type','document')->update(['type' => 'image']);
            $this->rebuildSqliteTable(['image','video','spin_360','try_on']);
        }
    }

    private function rebuildSqliteTable(array $types): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('product_media_document_type', function (Blueprint $table) use ($types): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type',$types);
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index(['product_id','type','sort_order']);
        });

        DB::statement('INSERT INTO product_media_document_type (id, uuid, product_id, type, disk, path, alt_text, sort_order, metadata, active, created_at, updated_at) SELECT id, uuid, product_id, type, disk, path, alt_text, sort_order, metadata, active, created_at, updated_at FROM product_media');
        Schema::drop('product_media');
        Schema::rename('product_media_document_type','product_media');
        Schema::enableForeignKeyConstraints();
    }

    private function replacePostgresTypeConstraint(array $types): void
    {
        $constraints = DB::select(<<<'SQL'
            SELECT constraint_name AS conname
            FROM information_schema.check_constraints
            WHERE constraint_schema = current_schema()
              AND constraint_name LIKE 'product_media%'
        SQL);

        foreach ($constraints as $constraint) {
            $name = str_replace('"','""',(string) $constraint->conname);
            DB::statement('ALTER TABLE "product_media" DROP CONSTRAINT "'.$name.'"');
        }

        $allowed = implode(', ', array_map(static fn (string $type): string => "'{$type}'", $types));
        DB::statement('ALTER TABLE "product_media" ADD CONSTRAINT "product_media_type_check" CHECK ("type" IN ('.$allowed.'))');
    }
};
