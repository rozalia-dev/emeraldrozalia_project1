<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBulkLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_deactivate_users_without_deleting_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
        $first = User::factory()->create(['status' => 'active']);
        $second = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.user-system.users.bulk'), [
                'ids' => [$first->id, $second->id],
                'action' => 'deactivate',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $first->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('users', ['id' => $second->id, 'status' => 'inactive']);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_admin_cannot_bulk_lock_or_deactivate_their_own_signed_in_account(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'status' => 'active',
            'locked_at' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.user-system.users'))
            ->post(route('admin.user-system.users.bulk'), [
                'ids' => [$admin->id],
                'action' => 'lock',
            ])
            ->assertRedirect(route('admin.user-system.users'))
            ->assertSessionHasErrors('ids');

        $admin->refresh();
        $this->assertNull($admin->locked_at);
        $this->assertSame('active', $admin->status);
    }

    public function test_media_bulk_endpoints_are_wired_into_the_admin_shell(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $script = file_get_contents(public_path('js/admin-table-actions.js'));

        $this->assertStringContainsString('/js/admin-table-actions.js', $layout);
        $this->assertStringContainsString('/admin/table-actions/product-media', $script);
        $this->assertStringContainsString('/admin/table-actions/site-media', $script);
        $this->assertStringContainsString('product-media-bulk-form', $script);
        $this->assertStringContainsString('site-media-bulk-form', $script);
    }
}
