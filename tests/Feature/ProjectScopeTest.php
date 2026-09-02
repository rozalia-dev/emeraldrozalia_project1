<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProjectScopeTest extends TestCase {
    use RefreshDatabase;
    public function test_public_core_pages_are_available():void {$this->get('/')->assertOk();$this->get('/shop')->assertOk();$this->get('/franchise')->assertOk();}
    public function test_external_services_default_to_disabled():void {foreach(config('external') as $service)$this->assertFalse($service['enabled']);}
    public function test_admin_has_all_six_order_masters():void {$admin=User::factory()->create(['is_admin'=>true]);foreach(['online','corporate','bulk','franchise','franchise_retail','buyer'] as $type)$this->actingAs($admin)->get('/admin/orders/'.$type)->assertOk();}
    public function test_excluded_modules_are_not_routable():void {$admin=User::factory()->create(['is_admin'=>true]);foreach(['production','finance','payroll','pos','hr'] as $module)$this->actingAs($admin)->get('/admin/resource/'.$module)->assertNotFound();}
}
