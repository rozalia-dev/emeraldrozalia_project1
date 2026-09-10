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
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('two_factor_enabled')->default(false);
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->unsignedBigInteger('reporting_to')->nullable()->index();
            $table->string('employee_code')->nullable()->unique();
            $table->timestampTz('password_changed_at')->nullable();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('type')->default('custom')->index();
            $table->unsignedSmallInteger('level')->default(5)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module')->nullable()->index();
            $table->string('action')->default('view')->index();
            $table->text('description')->nullable();
        });

        Schema::table('permission_role', function (Blueprint $table) {
            $table->string('access_level')->default('full');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->string('assignment_type')->default('primary')->index();
            $table->string('status')->default('active')->index();
            $table->unsignedBigInteger('assigned_by')->nullable()->index();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
        });

        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('type')->default('custom')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('permission_group_permission', function (Blueprint $table) {
            $table->foreignId('permission_group_id')->constrained('permission_groups')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_group_id', 'permission_id']);
        });

        Schema::create('permission_group_role', function (Blueprint $table) {
            $table->foreignId('permission_group_id')->constrained('permission_groups')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_group_id', 'role_id']);
        });

        $modules = [
            'Dashboard & System', 'Website & Products', 'Online Sales', 'Customers', 'Applications & Leads',
            'Territories', 'Agreements', 'Franchisees', 'Franchise Retail Stores', 'Training & Documents',
            'Marketing Assets', 'Performance & Targets', 'Renewals', 'Communication Center', 'Reports',
            'Users & Roles', 'Integrations', 'Settings', 'Audit & Logs', 'Data Management',
        ];
        $actions = ['view', 'create', 'edit', 'delete', 'export'];

        foreach ($modules as $module) {
            $slug = Str::slug($module, '.');
            foreach ($actions as $action) {
                $name = $slug.'.'.$action;
                $existing = DB::table('permissions')->where('name', $name)->first();
                $payload = [
                    'group' => $module,
                    'module' => $module,
                    'action' => $action,
                    'description' => ucfirst($action).' access for '.$module,
                    'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('permissions')->where('id', $existing->id)->update($payload);
                } else {
                    DB::table('permissions')->insert($payload + [
                        'uuid' => (string) Str::uuid(),
                        'name' => $name,
                        'created_at' => now(),
                    ]);
                }
            }
        }

        $roles = [
            ['Super Admin', 'Super Admin', 'system', 1, 'Full system access with all permissions.'],
            ['Operations Manager', 'Operations Manager', 'system', 2, 'Manage operations and daily activities.'],
            ['Finance Manager', 'Finance Manager', 'custom', 3, 'Manage financials, invoices, payments and reports.'],
            ['Marketing Manager', 'Marketing Manager', 'custom', 3, 'Manage marketing campaigns and assets.'],
            ['Store Manager', 'Store Manager', 'custom', 3, 'Manage retail stores and inventory.'],
            ['Sales Representative', 'Sales Representative', 'custom', 4, 'Handle leads, customers and sales.'],
            ['Accountant', 'Accountant', 'custom', 4, 'Accounting, payments and reconciliations.'],
            ['Support Manager', 'Support Manager', 'custom', 4, 'Manage support team and customer communications.'],
            ['Support Agent', 'Support Agent', 'custom', 5, 'Handle customer support requests.'],
            ['Content Creator', 'Content Creator', 'custom', 5, 'Create and manage website content.'],
            ['Viewer', 'Viewer', 'system', 6, 'Read-only access to system data.'],
            ['Franchise Manager', 'Franchise Manager', 'custom', 3, 'Manage franchise applications, stores, agreements and performance.'],
            ['API User', 'API User', 'api', 5, 'API integration role with controlled system access.'],
            ['Guest', 'Guest', 'system', 6, 'Minimal read-only access for guest contexts.'],
        ];

        foreach ($roles as [$name, $label, $type, $level, $description]) {
            $existing = DB::table('roles')->where('name', $name)->first();
            $payload = compact('label', 'type', 'level', 'description') + ['is_active' => true, 'updated_at' => now()];
            if ($existing) {
                DB::table('roles')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('roles')->insert($payload + [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'created_at' => now(),
                ]);
            }
        }

        $allPermissions = DB::table('permissions')->get(['id', 'module', 'action']);
        $roleRows = DB::table('roles')->get(['id', 'name']);
        foreach ($roleRows as $role) {
            foreach ($allPermissions as $permission) {
                $grant = match ($role->name) {
                    'Super Admin' => 'full',
                    'Viewer' => $permission->action === 'view' ? 'view' : null,
                    'Guest' => $permission->action === 'view' && in_array($permission->module,['Dashboard & System','Website & Products'],true) ? 'view' : null,
                    'API User' => in_array($permission->module,['Integrations','Data Management','Online Sales','Customers'],true) ? ($permission->action === 'delete' ? null : 'limited') : ($permission->action === 'view' ? 'view' : null),
                    'Finance Manager', 'Accountant' => str_contains((string) $permission->module, 'Sales') || str_contains((string) $permission->module, 'Reports') || str_contains((string) $permission->module, 'Settings') ? ($permission->action === 'delete' ? null : 'full') : ($permission->action === 'view' ? 'view' : null),
                    'Marketing Manager', 'Content Creator' => str_contains((string) $permission->module, 'Marketing') || str_contains((string) $permission->module, 'Website') ? ($permission->action === 'delete' ? 'limited' : 'full') : ($permission->action === 'view' ? 'view' : null),
                    'Franchise Manager' => str_contains((string) $permission->module, 'Franchise') || in_array($permission->module, ['Applications & Leads','Territories','Agreements','Training & Documents','Performance & Targets','Renewals','Reports'], true) ? ($permission->action === 'delete' ? 'limited' : 'full') : ($permission->action === 'view' ? 'view' : null),
                    default => $permission->action === 'view' ? 'view' : ($permission->action === 'delete' ? null : 'limited'),
                };
                if ($grant) {
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => $permission->id, 'role_id' => $role->id],
                        ['access_level' => $grant]
                    );
                }
            }
        }

        $groupDefinitions = [
            ['Dashboard & Reports', 'system', 'Access to dashboards, reports and analytics.', ['Dashboard & System', 'Reports']],
            ['User & Role Management', 'system', 'Manage users, roles, permissions and audit data.', ['Users & Roles', 'Audit & Logs']],
            ['Website Management', 'custom', 'Manage products, website content and settings.', ['Website & Products']],
            ['Sales Management', 'custom', 'Manage online sales, customers and reporting.', ['Online Sales', 'Customers', 'Reports']],
            ['Franchise Management', 'custom', 'Manage franchises and related data.', ['Applications & Leads','Territories','Agreements','Franchisees']],
            ['Retail Store Management', 'custom', 'Manage franchise retail stores and store users.', ['Franchise Retail Stores']],
            ['Marketing & Assets', 'custom', 'Manage marketing assets and campaigns.', ['Marketing Assets','Performance & Targets']],
            ['Training & Documents', 'custom', 'Manage training materials and documents.', ['Training & Documents']],
        ];
        foreach ($groupDefinitions as [$name, $type, $description, $permissionModules]) {
            $group = DB::table('permission_groups')->where('name', $name)->first();
            if (!$group) {
                $id = DB::table('permission_groups')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'name' => $name, 'type' => $type,
                    'description' => $description, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $id = $group->id;
                DB::table('permission_groups')->where('id', $id)->update(['type'=>$type,'description'=>$description,'is_active'=>true,'updated_at'=>now()]);
            }
            $permissionIds = DB::table('permissions')->whereIn('module', $permissionModules)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_group_permission')->insertOrIgnore(['permission_group_id' => $id, 'permission_id' => $permissionId]);
            }
        }

        $superRoleId = DB::table('roles')->where('name', 'Super Admin')->value('id');
        if ($superRoleId) {
            foreach (DB::table('users')->where('is_admin', true)->pluck('id') as $userId) {
                DB::table('role_user')->updateOrInsert(
                    ['role_id' => $superRoleId, 'user_id' => $userId],
                    ['assignment_type'=>'primary','status'=>'active','assigned_at'=>now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_group_role');
        Schema::dropIfExists('permission_group_permission');
        Schema::dropIfExists('permission_groups');

        Schema::table('role_user', fn (Blueprint $table) => $table->dropColumn(['assignment_type','status','assigned_by','assigned_at','expires_at']));
        Schema::table('permission_role', fn (Blueprint $table) => $table->dropColumn('access_level'));
        Schema::table('permissions', fn (Blueprint $table) => $table->dropColumn(['module','action','description']));
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['description','type','level','is_active','created_by']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['department','status','two_factor_enabled','locked_at','last_login_at','reporting_to','employee_code','password_changed_at']));
    }
};
