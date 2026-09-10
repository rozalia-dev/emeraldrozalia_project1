<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomerGroup;
use App\Models\CustomerProfile;
use App\Models\CustomerSegment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerManagementDashboardsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin'=>true]);
    }

    private function customer(string $name='Emma Walsh'): User
    {
        return User::factory()->create(['name'=>$name,'email'=>Str::slug($name).'@example.com','phone'=>'+353 87 654 3210','is_admin'=>false]);
    }

    public function test_all_three_customer_dashboards_render_against_real_domain_data(): void
    {
        $admin=$this->admin();$customer=$this->customer();
        $group=CustomerGroup::create(['uuid'=>(string)Str::uuid(),'name'=>'Retail Customers','slug'=>'retail-customers','type'=>'retail','description'=>'Individual retail customers','pricing_rule'=>'Standard Pricing','is_active'=>true,'created_by'=>$admin->id]);
        $segment=CustomerSegment::create(['uuid'=>(string)Str::uuid(),'name'=>'High Value Customers','slug'=>'high-value-customers','type'=>'value','description'=>'Customers with high lifetime value','is_active'=>true,'created_by'=>$admin->id]);
        CustomerProfile::create(['uuid'=>(string)Str::uuid(),'user_id'=>$customer->id,'primary_group_id'=>$group->id,'account_status'=>'active','country'=>'IE','preferred_language'=>'en','is_vip'=>true,'marketing_consent'=>true]);
        $customer->customerGroups()->attach($group);$customer->customerSegments()->attach($segment);
        Order::create(['user_id'=>$customer->id,'number'=>'ONL-CUST-001','order_type'=>'online','status'=>'delivered','payment_status'=>'paid','fulfillment_status'=>'shipped','subtotal'=>120,'shipping'=>0,'discount'=>0,'total'=>120,'currency'=>'EUR','currency_code'=>'EUR','exchange_rate'=>1,'email'=>$customer->email,'phone'=>$customer->phone]);

        $this->actingAs($admin)->get('/admin/customers')->assertOk()->assertSee(['Customer Management','Total Customers','Customer Profile','Account Summary','Order Category Distribution','Spending Overview','Top Purchased Categories','Communication History','Emma Walsh','/css/customers-admin.css?v=20260910-customer-v1'],false);
        $this->actingAs($admin)->get('/admin/customer-groups')->assertOk()->assertSee(['Customer Groups','Retail Customers','Group Details','Group Summary','Group Type Distribution','Pricing Rule Distribution'],false);
        $this->actingAs($admin)->get('/admin/customer-segments')->assertOk()->assertSee(['Customer Segments','High Value Customers','Segment Details','Segment Summary','Segment Type Distribution','RFM Segments Coverage'],false);
    }

    public function test_admin_can_create_and_update_customer_with_group_segment_and_audit(): void
    {
        $admin=$this->admin();
        $group=CustomerGroup::create(['uuid'=>(string)Str::uuid(),'name'=>'Corporate Accounts','slug'=>'corporate-accounts','type'=>'corporate','is_active'=>true]);
        $segment=CustomerSegment::create(['uuid'=>(string)Str::uuid(),'name'=>'VIP Customers','slug'=>'vip-customers','type'=>'value','is_active'=>true]);
        $this->actingAs($admin)->post('/admin/customers',['name'=>'Emerald Fashion Ltd.','email'=>'accounts@emeraldfashion.ie','phone'=>'+353 87 111 2222','account_status'=>'active','country'=>'IE','preferred_language'=>'en','group_id'=>$group->id,'segment_ids'=>[$segment->id],'is_vip'=>'1','marketing_consent'=>'1','notes'=>'Corporate customer'])->assertRedirect();
        $customer=User::where('email','accounts@emeraldfashion.ie')->firstOrFail();
        $this->assertDatabaseHas('customer_profiles',['user_id'=>$customer->id,'primary_group_id'=>$group->id,'account_status'=>'active','country'=>'IE','is_vip'=>true]);
        $this->assertTrue($customer->customerGroups()->whereKey($group->id)->exists());$this->assertTrue($customer->customerSegments()->whereKey($segment->id)->exists());
        $this->assertTrue(AuditLog::where('action','customer.created')->where('subject_id',$customer->id)->exists());
        $this->actingAs($admin)->patch('/admin/customers/'.$customer->id,['name'=>'Emerald Fashion Ireland','email'=>'accounts@emeraldfashion.ie','phone'=>'+353 87 111 3333','account_status'=>'restricted','country'=>'IE','preferred_language'=>'en','group_id'=>$group->id,'segment_ids'=>[$segment->id],'is_vip'=>'1','marketing_consent'=>'1','notes'=>'Updated'])->assertRedirect();
        $this->assertDatabaseHas('users',['id'=>$customer->id,'name'=>'Emerald Fashion Ireland','phone'=>'+353 87 111 3333']);$this->assertDatabaseHas('customer_profiles',['user_id'=>$customer->id,'account_status'=>'restricted']);
    }

    public function test_group_and_segment_workflows_support_membership_duplicate_and_uuid_search(): void
    {
        $admin=$this->admin();$customer=$this->customer('John Smith');
        $this->actingAs($admin)->post('/admin/customer-groups',['name'=>'Franchise Customers','type'=>'franchise','description'=>'Franchise partners','pricing_rule'=>'Franchise Pricing','discount_note'=>'Partner Discounts','country'=>'IE','is_active'=>'1','is_vip'=>'0','customer_ids'=>[$customer->id]])->assertRedirect();
        $group=CustomerGroup::where('name','Franchise Customers')->firstOrFail();$this->assertTrue($group->customers()->whereKey($customer->id)->exists());
        $this->actingAs($admin)->get('/admin/customer-groups?q='.substr($group->uuid,0,8))->assertOk()->assertSee('Franchise Customers',false);
        $this->actingAs($admin)->post('/admin/customer-groups/'.$group->id.'/duplicate')->assertRedirect();$this->assertDatabaseCount('customer_groups',2);

        $this->actingAs($admin)->post('/admin/customer-segments',['name'=>'Repeat Buyers','type'=>'behavior','description'=>'Customers who purchased repeatedly','country'=>'IE','rule_definition'=>'orders_count >= 2','is_active'=>'1','customer_ids'=>[$customer->id]])->assertRedirect();
        $segment=CustomerSegment::where('name','Repeat Buyers')->firstOrFail();$this->assertTrue($segment->customers()->whereKey($customer->id)->exists());
        $this->actingAs($admin)->get('/admin/customer-segments?q='.substr($segment->uuid,0,8))->assertOk()->assertSee('Repeat Buyers',false);
        $this->actingAs($admin)->post('/admin/customer-segments/'.$segment->id.'/duplicate')->assertRedirect();$this->assertDatabaseCount('customer_segments',2);
    }

    public function test_customer_group_and_segment_csv_workflows_are_real(): void
    {
        $admin=$this->admin();$customer=$this->customer('CSV Customer');
        CustomerProfile::create(['uuid'=>(string)Str::uuid(),'user_id'=>$customer->id,'account_status'=>'active','country'=>'IE']);
        $this->actingAs($admin)->get('/admin/customers/export')->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
        $groups="name,type,description,pricing_rule,discount_note,country,status,vip\nBulk Buyers,bulk,Bulk customers,Volume Pricing,Volume Discounts,IE,active,0\n";
        $this->actingAs($admin)->post('/admin/customer-groups/import',['file'=>UploadedFile::fake()->createWithContent('groups.csv',$groups)])->assertRedirect();$this->assertDatabaseHas('customer_groups',['name'=>'Bulk Buyers','type'=>'bulk']);
        $segments="name,type,description,country,status\nIreland Customers,geographic,Customers located in Ireland,IE,active\n";
        $this->actingAs($admin)->post('/admin/customer-segments/import',['file'=>UploadedFile::fake()->createWithContent('segments.csv',$segments)])->assertRedirect();$this->assertDatabaseHas('customer_segments',['name'=>'Ireland Customers','type'=>'geographic']);
        $this->actingAs($admin)->get('/admin/customer-groups/export')->assertOk();$this->actingAs($admin)->get('/admin/customer-segments/export')->assertOk();
    }
}
