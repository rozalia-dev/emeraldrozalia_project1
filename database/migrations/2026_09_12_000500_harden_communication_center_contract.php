<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addConversationColumns();
        $this->addMessageColumns();

        if (! Schema::hasTable('communication_webhook_events')) {
            Schema::create('communication_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();
                $table->string('provider', 50);
                $table->string('external_event_id', 180);
                $table->string('event_type', 100)->nullable();
                $table->uuid('message_uuid')->nullable()->index();
                $table->string('signature_digest', 64);
                $table->jsonb('payload')->nullable();
                $table->string('status', 40)->default('received')->index();
                $table->unsignedSmallInteger('attempts')->default(1);
                $table->text('failure_reason')->nullable();
                $table->timestampTz('processed_at')->nullable();
                $table->timestampsTz();
                $table->unique(['provider', 'external_event_id']);
            });
        }

        // Existing enquiries already carry the company boundary. Backfill only
        // from that trusted relation; unresolved legacy records remain visible
        // to the admin context instead of being guessed into a tenant.
        if (Schema::hasTable('conversations') && Schema::hasTable('inquiries')) {
            DB::table('conversations')
                ->whereNull('conversations.company_id')
                ->whereNotNull('conversations.inquiry_id')
                ->join('inquiries', 'inquiries.id', '=', 'conversations.inquiry_id')
                ->whereNotNull('inquiries.company_id')
                ->select('conversations.id', 'inquiries.company_id')
                ->orderBy('conversations.id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('conversations')
                            ->where('id', $row->id)
                            ->whereNull('company_id')
                            ->update(['company_id' => $row->company_id]);
                    }
                }, 'conversations.id', 'id');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_webhook_events');

        foreach (['company_id', 'customer_id', 'order_id', 'store_id'] as $column) {
            $this->dropColumnIfPresent('conversations', $column);
        }
        foreach (['idempotency_key', 'request_hash', 'consent_captured_at', 'consent_version'] as $column) {
            $this->dropColumnIfPresent('conversations', $column);
        }
        foreach (['idempotency_key', 'provider_message_id', 'delivery_attempts', 'delivered_at', 'failed_at', 'failure_code', 'failure_message'] as $column) {
            $this->dropColumnIfPresent('conversation_messages', $column);
        }
    }

    private function addConversationColumns(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversations', 'company_id')) {
                $table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->index()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'order_id')) {
                $table->foreignId('order_id')->nullable()->index()->constrained('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'store_id')) {
                $table->foreignId('store_id')->nullable()->index()->constrained('stores')->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique();
            }
            if (! Schema::hasColumn('conversations', 'request_hash')) {
                $table->string('request_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('conversations', 'consent_captured_at')) {
                $table->timestampTz('consent_captured_at')->nullable();
            }
            if (! Schema::hasColumn('conversations', 'consent_version')) {
                $table->string('consent_version', 80)->nullable();
            }
        });
    }

    private function addMessageColumns(): void
    {
        if (! Schema::hasTable('conversation_messages')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversation_messages', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique();
            }
            if (! Schema::hasColumn('conversation_messages', 'provider_message_id')) {
                $table->string('provider_message_id', 180)->nullable()->index();
            }
            if (! Schema::hasColumn('conversation_messages', 'delivery_attempts')) {
                $table->unsignedSmallInteger('delivery_attempts')->default(0);
            }
            if (! Schema::hasColumn('conversation_messages', 'delivered_at')) {
                $table->timestampTz('delivered_at')->nullable();
            }
            if (! Schema::hasColumn('conversation_messages', 'failed_at')) {
                $table->timestampTz('failed_at')->nullable();
            }
            if (! Schema::hasColumn('conversation_messages', 'failure_code')) {
                $table->string('failure_code', 80)->nullable();
            }
            if (! Schema::hasColumn('conversation_messages', 'failure_message')) {
                $table->text('failure_message')->nullable();
            }
        });
    }

    private function dropColumnIfPresent(string $tableName, string $column): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) !== [$column]) {
                continue;
            }

            $name = $foreignKey['name'] ?? null;
            if ($name) {
                Schema::table($tableName, function (Blueprint $table) use ($name): void {
                    $table->dropForeign($name);
                });
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }
};
