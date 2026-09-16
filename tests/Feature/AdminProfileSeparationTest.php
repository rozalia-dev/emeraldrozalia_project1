<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProfileSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_route_redirects_to_cpanel_profile(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('account.dashboard'))
            ->assertRedirect(route('admin.profile.show'));

        $this->actingAs($admin)
            ->get(route('admin.profile.show'))
            ->assertOk()
            ->assertSee('ADMIN ACCOUNT')
            ->assertSee('My Profile');
    }

    public function test_customer_keeps_existing_customer_account_and_cannot_open_admin_profile(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)
            ->get(route('account.dashboard'))
            ->assertOk();

        $this->actingAs($customer)
            ->get(route('admin.profile.show'))
            ->assertForbidden();
    }

    public function test_admin_can_update_cpanel_profile_without_using_customer_profile_flow(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'name' => 'Admin User',
            'phone' => null,
            'department' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.profile.update'), [
                'name' => 'Control Panel Admin',
                'phone' => '+353 61 000 000',
                'department' => 'Administration',
            ])
            ->assertRedirect();

        $admin->refresh();

        $this->assertSame('Control Panel Admin', $admin->name);
        $this->assertSame('+353 61 000 000', $admin->phone);
        $this->assertSame('Administration', $admin->department);
    }
}
