<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderMasterOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'number' => 'ONL-'.strtoupper(Str::random(8)),
            'order_type' => 'online',
            'status' => 'processing',
            'payment_status' => 'paid',
            'fulfillment_status' => 'picking',
            'subtotal' => 100,
            'shipping' => 5,
            'discount' => 0,
            'total' => 105,
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'exchange_rate' => 1,
            'email' => 'customer@example.com',
            'phone' => '+353 89 123 4567',
            'payment_method' => 'card',
            'shipping_address' => ['name' => 'Emerald Customer'],
        ], $overrides));
    }

    public function test_order_master_overview_renders_the_reference_dashboard_contract(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        OrderItem::create(['order_id'=>$order->id,'name'=>'Emerald Signature Cap','sku'=>'ER-CAP-001','unit_price'=>105,'quantity'=>1,'total'=>105]);

        $this->actingAs($admin)->get('/admin/order-master')
            ->assertOk()
            ->assertSee([
                'Order Master Overview','Central view of all order categories and overall order performance.',
                'Online Orders','Corporate Orders','Bulk Orders','Franchise Orders','Franchise Retail Orders','Buyer Orders',
                'Pending Approval / Payment','ORDER MASTER SUMMARY','QUICK ACTIONS','ORDER NOTIFICATIONS',
                'Orders by Category','Top Selling Products','Order Value by Category','Order Status Overview',
                '/css/order-master.css?v=20260910-order-master-v1','/js/order-master.js?v=20260910-order-master-v1',
            ], false)
            ->assertSee($order->number, false)
            ->assertSee('Emerald Signature Cap', false);
    }

    public function test_master_filters_category_payment_fulfillment_status_and_search(): void
    {
        $admin = $this->admin();
        $match = $this->order([
            'number'=>'CORP-FILTER-001','order_type'=>'corporate','status'=>'approved','payment_status'=>'paid',
            'fulfillment_status'=>'ready_to_ship','payment_method'=>'bank_transfer','email'=>'orders@filter-company.ie',
            'shipping_address'=>['name'=>'Filter Company Ltd'],
        ]);
        $this->order(['number'=>'BULK-OTHER-001','order_type'=>'bulk','fulfillment_status'=>'picking','payment_method'=>'card','email'=>'other@example.com','shipping_address'=>['name'=>'Other Company']]);

        $url='/admin/order-master?'.http_build_query(['q'=>'filter-company','order_type'=>'corporate','payment_method'=>'bank_transfer','fulfillment_status'=>'ready_to_ship','status'=>'approved']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee($match->number,false)->assertDontSee('BULK-OTHER-001',false);
    }

    public function test_admin_can_create_order_and_audit_it(): void
    {
        $admin=$this->admin();
        $this->actingAs($admin)->post('/admin/order-master',[
            'order_type'=>'buyer','customer_name'=>'John Buyer','email'=>'john@example.com','phone'=>'+353 89 000 0000',
            'subtotal'=>250,'shipping'=>10,'discount'=>20,'status'=>'pending','payment_status'=>'pending','payment_method'=>'card',
            'fulfillment_status'=>'on_hold','notes'=>'Manual cPanel order',
        ])->assertRedirect(route('admin.order-master.overview'));

        $order=Order::query()->where('email','john@example.com')->firstOrFail();
        $this->assertSame('buyer',$order->order_type); $this->assertSame('on_hold',$order->fulfillment_status);
        $this->assertEquals(240,(float)$order->total); $this->assertSame('John Buyer',data_get($order->shipping_address,'name'));
        $this->assertTrue(AuditLog::query()->where('action','order.created.admin')->where('subject_id',$order->id)->exists());
    }

    public function test_csv_export_and_import_are_real_order_workflows(): void
    {
        $admin=$this->admin(); $order=$this->order(['number'=>'ONL-EXPORT-001']);
        $response=$this->actingAs($admin)->get('/admin/order-master/export?order_type=online');
        $response->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
        $this->assertStringContainsString($order->number,$response->streamedContent());
        $this->assertStringContainsString('fulfillment_status',$response->streamedContent());

        $csv=implode("\n",[
            'number,order_type,customer_name,email,phone,status,payment_status,payment_method,fulfillment_status,subtotal,shipping,discount,total,currency_code',
            'BULK-IMPORT-001,bulk,Imported Company,import@example.com,+353890001111,approved,paid,bank_transfer,picking,500,25,10,515,EUR',
        ]);
        $this->actingAs($admin)->post('/admin/order-master/import',['file'=>UploadedFile::fake()->createWithContent('orders.csv',$csv)])
            ->assertRedirect(route('admin.order-master.overview'));
        $this->assertDatabaseHas('orders',['number'=>'BULK-IMPORT-001','order_type'=>'bulk','fulfillment_status'=>'picking','total'=>515]);
    }

    public function test_existing_type_and_online_sales_resource_routes_remain_functional(): void
    {
        $admin=$this->admin(); $this->order(['order_type'=>'franchise','number'=>'FRAN-KEEP-001']);
        $this->actingAs($admin)->get(route('admin.order-master','franchise'))->assertOk()->assertSee('FRAN-KEEP-001',false);
        $this->actingAs($admin)->get('/admin/resource/online-sales')->assertOk()->assertSee('Online Sales',false);
    }

    public function test_shared_admin_sidebar_has_one_order_navigation_source(): void
    {
        $admin=$this->admin();
        $response=$this->actingAs($admin)->get(route('admin.order-master.overview'))->assertOk();
        $html=$response->getContent();

        $this->assertSame(1, substr_count($html, 'id="admin-sidebar"'));
        $this->assertStringContainsString(route('admin.order-master.overview'), $html);
        $this->assertStringNotContainsString('ORDER MANAGEMENT (6 CATEGORIES)', $html);

        foreach (['online','corporate','bulk','franchise','franchise_retail','buyer'] as $type) {
            $this->assertStringContainsString(route('admin.order-master', $type), $html);
        }
    }
}
