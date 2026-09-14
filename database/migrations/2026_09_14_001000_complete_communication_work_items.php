<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communication_actions')) {
            Schema::create('communication_actions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->index()->constrained('conversations')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->index()->constrained('orders')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('legacy_admin_record_id')->nullable()->unique();
                $table->string('reference', 100)->nullable()->index();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('category', 120)->nullable();
                $table->string('source', 120)->nullable();
                $table->string('entity', 180)->nullable();
                $table->string('priority', 20)->default('normal')->index();
                $table->string('status', 30)->default('pending')->index();
                $table->decimal('amount', 14, 2)->nullable();
                $table->date('record_date')->nullable()->index();
                $table->timestampTz('due_at')->nullable()->index();
                $table->timestampTz('completed_at')->nullable();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->string('request_hash', 64)->nullable();
                $table->jsonb('data')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestampsTz();
                $table->softDeletesTz();
            });
        }

        if (! Schema::hasTable('communication_alerts')) {
            Schema::create('communication_alerts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->index()->constrained('conversations')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->index()->constrained('orders')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('legacy_admin_record_id')->nullable()->unique();
                $table->string('reference', 100)->nullable()->index();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('type', 120)->nullable();
                $table->string('category', 120)->nullable();
                $table->string('source', 120)->nullable();
                $table->string('entity', 180)->nullable();
                $table->string('severity', 30)->default('low')->index();
                $table->string('status', 30)->default('unread')->index();
                $table->date('record_date')->nullable()->index();
                $table->timestampTz('due_at')->nullable()->index();
                $table->timestampTz('acknowledged_at')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->string('request_hash', 64)->nullable();
                $table->jsonb('data')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestampsTz();
                $table->softDeletesTz();
            });
        }

        $this->backfill('action-follow-ups', 'communication_actions');
        $this->backfill('alerts-notifications', 'communication_alerts');
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_alerts');
        Schema::dropIfExists('communication_actions');
    }

    private function backfill(string $module, string $table): void
    {
        if (! Schema::hasTable('admin_records') || ! Schema::hasTable($table)) {
            return;
        }

        DB::table('admin_records')
            ->where('module', $module)
            ->orderBy('id')
            ->get()
            ->each(function (object $legacy) use ($module, $table): void {
                $metadata = is_array($legacy->data ?? null)
                    ? $legacy->data
                    : (json_decode((string) ($legacy->data ?? '{}'), true) ?: []);

                $isAlert = $module === 'alerts-notifications';
                $attributes = [
                    'uuid' => (string) Str::uuid(),
                    'company_id' => $legacy->company_id ?? null,
                    'created_by' => $legacy->user_id ?? null,
                    'legacy_admin_record_id' => $legacy->id,
                    'reference' => $legacy->reference,
                    'title' => $legacy->title,
                    'description' => data_get($metadata, 'description', data_get($metadata, 'notes')),
                    'category' => data_get($metadata, 'category'),
                    'source' => data_get($metadata, 'source'),
                    'entity' => data_get($metadata, 'entity'),
                    'priority' => data_get($metadata, 'priority', 'normal'),
                    'status' => $legacy->status ?: ($isAlert ? 'unread' : 'pending'),
                    'record_date' => $legacy->record_date,
                    'due_at' => data_get($metadata, 'due_at'),
                    'data' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'version' => 1,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at,
                    'deleted_at' => $legacy->deleted_at ?? null,
                ];

                if ($isAlert) {
                    $attributes['type'] = data_get($metadata, 'type');
                    $attributes['severity'] = data_get($metadata, 'severity', 'low');
                } else {
                    $attributes['amount'] = $legacy->amount;
                    if (($attributes['status'] ?? null) === 'completed') {
                        $attributes['completed_at'] = $legacy->updated_at;
                    }
                }

                DB::table($table)->insertOrIgnore($attributes);
            });
    }
};
