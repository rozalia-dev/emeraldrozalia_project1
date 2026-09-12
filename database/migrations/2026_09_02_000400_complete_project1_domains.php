<?php

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PUBLIC_UUID_TABLES = [
        'users',
        'categories',
        'products',
        'orders',
        'order_items',
        'inquiries',
        'stores',
        'product_variants',
        'addresses',
        'wishlists',
        'reviews',
        'returns',
        'reward_transactions',
        'inventory_movements',
        'payment_transactions',
        'shipping_methods',
        'discounts',
        'admin_records',
    ];

    public function up(): void
    {
        foreach (self::PUBLIC_UUID_TABLES as $table) {
            $this->ensureUuidColumn($table, 'public_uuid');
        }

        $this->ensureUuidColumn('content_pages', 'uuid');
        $this->addContentPageColumns();

        $this->createTableIfMissing('page_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->jsonb('schema');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        $this->createTableIfMissing('page_sections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('content_page_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('settings');
            $table->boolean('visible')->default(true);
            $table->timestampsTz();
            $table->index(['content_page_id', 'sort_order']);
        });

        $this->createTableIfMissing('page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('content_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('snapshot');
            $table->string('reason')->nullable();
            $table->timestampsTz();
            $table->unique(['content_page_id', 'version']);
        });

        $this->createTableIfMissing('product_media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'video', 'spin_360', 'try_on']);
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index(['product_id', 'type', 'sort_order']);
        });

        $this->createTableIfMissing('franchise_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('applicant_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('territory');
            $table->string('preferred_location')->nullable();
            $table->string('investment_range')->nullable();
            $table->text('business_experience')->nullable();
            $table->string('status')->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('follow_up_at')->nullable()->index();
            $table->jsonb('data')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('franchise_stores', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('franchise_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('territory');
            $table->jsonb('address');
            $table->string('status')->default('onboarding')->index();
            $table->date('opened_at')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('franchise_milestones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('franchise_application_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['territory', 'agreement', 'training', 'store_setup', 'marketing', 'performance', 'renewal']);
            $table->string('status')->default('pending')->index();
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('channel', ['web', 'chat', 'whatsapp', 'email', 'phone', 'system']);
            $table->string('contact');
            $table->string('subject')->nullable();
            $table->string('priority')->default('normal')->index();
            $table->string('status')->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('follow_up_at')->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['inbound', 'outbound', 'internal']);
            $table->text('body');
            $table->string('delivery_status')->default('stored');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('channel', ['email', 'whatsapp', 'chat']);
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('status')->default('draft')->index();
            $table->jsonb('variables')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('approvable');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('decision_note')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestampsTz();
        });

        $this->createTableIfMissing('permissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('group')->index();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $this->createTableIfMissing('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        $this->createTableIfMissing('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('service')->unique();
            $table->string('provider')->nullable();
            $table->boolean('enabled')->default(false);
            $table->text('encrypted_credentials')->nullable();
            $table->string('health')->default('not_configured');
            $table->timestampTz('tested_at')->nullable();
            $table->timestampsTz();
        });

        $this->createTableIfMissing('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->uuid('request_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        $this->createTableIfMissing('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('event');
            $table->jsonb('conditions');
            $table->jsonb('actions');
            $table->boolean('enabled')->default(false);
            $table->timestampsTz();
        });

        $this->createTableIfMissing('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('status')->index();
            $table->string('location')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('metadata')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'backup_runs',
            'automation_rules',
            'audit_logs',
            'integration_connections',
            'role_user',
            'permission_role',
            'permissions',
            'roles',
            'approvals',
            'communication_templates',
            'conversation_messages',
            'conversations',
            'franchise_milestones',
            'franchise_stores',
            'franchise_applications',
            'product_media',
            'page_revisions',
            'page_sections',
            'page_templates',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $this->dropColumnsIfPresent('content_pages', [
            'uuid',
            'locale',
            'template',
            'navigation_visible',
            'scheduled_for',
            'published_at',
            'archived_at',
            'deleted_at',
        ]);

        foreach (self::PUBLIC_UUID_TABLES as $table) {
            $this->dropColumnsIfPresent($table, ['public_uuid']);
        }
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

    private function addContentPageColumns(): void
    {
        $this->addColumnIfMissing('content_pages', 'locale', static function (Blueprint $table): void {
            $table->string('locale', 10)->default('en')->index();
        });
        $this->addColumnIfMissing('content_pages', 'template', static function (Blueprint $table): void {
            $table->string('template')->default('standard');
        });
        $this->addColumnIfMissing('content_pages', 'navigation_visible', static function (Blueprint $table): void {
            $table->boolean('navigation_visible')->default(false);
        });
        $this->addColumnIfMissing('content_pages', 'scheduled_for', static function (Blueprint $table): void {
            $table->timestampTz('scheduled_for')->nullable()->index();
        });
        $this->addColumnIfMissing('content_pages', 'published_at', static function (Blueprint $table): void {
            $table->timestampTz('published_at')->nullable()->index();
        });
        $this->addColumnIfMissing('content_pages', 'archived_at', static function (Blueprint $table): void {
            $table->timestampTz('archived_at')->nullable();
        });
        $this->addColumnIfMissing('content_pages', 'deleted_at', static function (Blueprint $table): void {
            $table->softDeletesTz();
        });
    }

    private function addColumnIfMissing(string $table, string $column, Closure $definition): void
    {
        if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
            Schema::table($table, $definition);
        }
    }

    private function createTableIfMissing(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    private function dropColumnsIfPresent(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, static function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};
