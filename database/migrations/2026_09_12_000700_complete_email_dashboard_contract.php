<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversations', 'deleted_at')) {
                $table->softDeletesTz();
            }
        });

        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasIndex('conversations', 'conversations_channel_status_updated_at_index')) {
                $table->index(['channel', 'status', 'updated_at'], 'conversations_channel_status_updated_at_index');
            }
            if (! Schema::hasIndex('conversations', 'conversations_channel_created_at_index')) {
                $table->index(['channel', 'created_at'], 'conversations_channel_created_at_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            if (Schema::hasIndex('conversations', 'conversations_channel_status_updated_at_index')) {
                $table->dropIndex('conversations_channel_status_updated_at_index');
            }
            if (Schema::hasIndex('conversations', 'conversations_channel_created_at_index')) {
                $table->dropIndex('conversations_channel_created_at_index');
            }
            if (Schema::hasColumn('conversations', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
