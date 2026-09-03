<?php
namespace Tests\Feature;
use App\Models\{Address,AuditLog,Category,Company,Discount,InventoryMovement,Order,PaymentTransaction,Product,ProductMedia,ProductVariant,ReturnRequest,ShippingMethod,User};
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\{Event,Hash,Notification,Route,URL};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProjectScopeTest extends TestCase {
    use RefreshDatabase;
    public function test_public_core_pages_are_available():void {$this->get('/')->assertOk();$this->get('/shop')->assertOk();$this->get('/franchise')->assertOk();}
    public function test_foundation_routes_and_health_endpoint_are_available():void {foreach(['/','/shop','/collections','/new-arrivals','/corporate-orders','/bulk-orders','/franchise','/careers','/global-network','/factory','/contact','/virtual-tryon','/irish-traditional','/irish-heritage','/cart','/login','/register','/up'] as $path)$this->get($path)->assertOk();}
    public function test_homepage_exposes_approved_section_sequence():void {$this->get('/')->assertOk()->assertSee(['CRAFTED IN','VIRTUAL TRY-ON','SHOP BY COLLECTION','THE IRISH HERITAGE COLLECTION','BESTSELLERS','QUALITY IN EVERY STITCH.','FRANCHISE OPEN NOW'],false);}
    public function test_catalogue_screens_have_dedicated_content():void {$this->get('/collections')->assertOk()->assertSee('OUR COLLECTIONS');$this->get('/new-arrivals')->assertOk()->assertSee(['NEW ARRIVALS','APPLY FILTERS'],false);$this->get('/shop?q=cap')->assertOk()->assertSee('Search');}
    public function test_product_detail_renders_active_variants_and_catalogue_data():void {$product=Product::create(['name'=>'Variant Cap','slug'=>'variant-cap','sku'=>'VAR-001','price'=>34.99,'stock'=>10,'description'=>'A test product','is_active'=>true]);ProductVariant::create(['product_id'=>$product->id,'sku'=>'VAR-001-GREEN','colour'=>'Emerald','size'=>'M/L','price'=>39.99,'stock'=>4,'is_active'=>true]);ProductVariant::create(['product_id'=>$product->id,'sku'=>'VAR-001-RETIRED','colour'=>'Retired','size'=>'One Size','price'=>29.99,'stock'=>0,'is_active'=>false]);$this->get('/product/'.$product->slug)->assertOk()->assertSee(['Variant Cap','VAR-001','Emerald','39.99','ADD TO CART'],false)->assertDontSee('Retired');}
    public function test_media_manager_lists_and_validates_product_media():void {$admin=User::factory()->create(['is_admin'=>true]);$product=Product::create(['name'=>'Media Cap','slug'=>'media-cap','sku'=>'MED-001','price'=>24.99,'stock'=>5,'is_active'=>true]);Storage::fake('public');Storage::disk('public')->put('product-media/test.webp','media');$this->actingAs($admin)->post('/admin/resource/media-manager',['product_id'=>$product->id,'type'=>'image','disk'=>'public','path'=>'product-media/test.webp','alt_text'=>'Front view','sort_order'=>0,'active'=>1])->assertRedirect();$media=ProductMedia::firstOrFail();$this->actingAs($admin)->get('/admin/resource/media-manager?product_id='.$product->id)->assertOk()->assertSee(['Product Media Manager','Front view','product-media/test.webp'],false);$this->actingAs($admin)->patch('/admin/resource/media-manager/'.$media->id,['type'=>'image','alt_text'=>'Updated front view','sort_order'=>1,'active'=>1])->assertRedirect();$this->assertSame('Updated front view',$media->fresh()->alt_text);$this->actingAs($admin)->delete('/admin/resource/media-manager/'.$media->id)->assertRedirect();$this->assertDatabaseMissing('product_media',['id'=>$media->id]);}
    public function test_product_detail_exposes_360_viewer_contract():void {$product=Product::create(['name'=>'Spin Cap','slug'=>'spin-cap','sku'=>'SPIN-001','price'=>44.99,'stock'=>8,'spin_images'=>['spin/frame-01.webp','spin/frame-02.webp'],'is_active'=>true]);ProductMedia::create(['product_id'=>$product->id,'type'=>'spin_360','disk'=>'public','path'=>'spin/frame-03.webp','alt_text'=>'Rear angle','sort_order'=>2,'active'=>true]);$this->get('/product/'.$product->slug)->assertOk()->assertSee(['data-spin-viewer','data-images','frame-01.webp','frame-03.webp'],false);}
    public function test_virtual_try_on_is_local_and_uses_product_assets_only():void {$product=Product::create(['name'=>'Try-On Cap','slug'=>'try-on-cap','sku'=>'TRY-001','price'=>29.99,'stock'=>3,'is_active'=>true]);Storage::fake('public');Storage::disk('public')->put('try-on/try-cap.png','asset');ProductMedia::create(['product_id'=>$product->id,'type'=>'try_on','disk'=>'public','path'=>'try-on/try-cap.png','alt_text'=>'Try-On cap','sort_order'=>0,'active'=>true]);$this->get('/virtual-tryon?product='.$product->id)->assertOk()->assertSee(['data-try-studio','data-face-upload','never sent to the server','try-cap.png'],false);}
    public function test_informational_category_and_factory_pages_are_dedicated():void {$this->get('/irish-traditional')->assertOk()->assertSee(['IRISH TRADITIONAL','FLAT CAPS','APPLY FILTERS'],false);$this->get('/irish-heritage')->assertOk()->assertSee(['IRISH HERITAGE','HATS','APPLY FILTERS'],false);$this->get('/factory')->assertOk()->assertSee(['HOW WE','FROM CONCEPT TO CREATION','WELCOME TO VISIT OUR FACTORY'],false);}
    public function test_business_and_company_pages_are_dedicated():void {$this->get('/corporate-orders')->assertOk()->assertSee(['CORPORATE ORDERS','HOW IT WORKS','REQUEST A QUOTE'],false);$this->get('/bulk-orders')->assertOk()->assertSee(['BULK ORDER SOLUTIONS','REQUEST A BULK QUOTE'],false);$this->get('/franchise')->assertOk()->assertSee(['FRANCHISE WITH','WHY PARTNER WITH US?','SUBMIT ENQUIRY'],false);$this->get('/careers')->assertOk()->assertSee(['BUILD YOUR CAREER','CURRENT OPEN POSITIONS','SUBMIT APPLICATION'],false);$this->get('/global-network')->assertOk()->assertSee(['OUR GLOBAL','A GLOBAL PRESENCE','LIMERICK, IRELAND'],false);$this->get('/contact')->assertOk()->assertSee(['WE\'RE HERE','SEND US A MESSAGE','FREQUENTLY ASKED QUESTIONS'],false);}
    public function test_authenticated_boundaries_redirect_guests():void {$this->get('/account')->assertRedirect('/login');$this->get('/checkout')->assertRedirect('/login');$this->get('/admin')->assertRedirect('/login');}
    public function test_secure_url_setting_protects_every_form_target():void {$previous=(bool)config('app.force_https');config(['app.force_https'=>true]);(new AppServiceProvider($this->app))->boot();try{$this->get('/login')->assertOk()->assertSee(['action="https://localhost/login"','action="https://localhost/register"','href="https://localhost/forgot-password"'],false);}finally{URL::forceScheme(null);config(['app.force_https'=>$previous]);}}
    public function test_admin_navigation_nests_product_operations_under_products():void {$admin=User::factory()->create(['is_admin'=>true]);$response=$this->actingAs($admin)->get('/admin');$response->assertOk()->assertSee(['Products','Product Manager','Add Product','Bulk Product Upload','Product Media Manager','Images','Videos','360° Product View','Virtual Try-On','Categories','Collections','Variants','Pages'],false);$content=$response->getContent();$this->assertSame(1,substr_count($content,'class="admin-nav-subgroup"'));$this->assertSame(0,substr_count($content,'class="admin-nav-subgroup" open'));$this->assertSame(1,substr_count($content,'class="admin-nav-subitems"'));$this->assertLessThan(strpos($content,'Product Manager'),strpos($content,'class="admin-nav-subgroup"'));}
    public function test_customer_auth_lifecycle_email_state_and_rate_limits():void {
        Event::fake([Registered::class]);
        $response=$this->post('/register',['name'=>'Aoife Customer','email'=>'aoife@example.com','phone'=>'0890000000','password'=>'SecurePass1','password_confirmation'=>'SecurePass1']);
        $response->assertRedirect('/account');
        $user=User::where('email','aoife@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('SecurePass1',$user->password));
        $this->assertNull($user->email_verified_at);
        Event::assertDispatched(Registered::class);
        $this->get('/account')->assertOk()->assertSee(['Welcome back, Aoife!','Verify your email address','RESEND EMAIL'],false);
        $this->post('/email/verification-notification')->assertRedirect()->assertSessionHas('success','Verification link sent.');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertContains('throttle:5,1',Route::getRoutes()->getByName('login.submit')->gatherMiddleware());
        $this->assertContains('throttle:5,10',Route::getRoutes()->getByName('register')->gatherMiddleware());
        $this->assertContains('throttle:5,10',Route::getRoutes()->getByName('password.email')->gatherMiddleware());
        $this->assertContains('throttle:5,10',Route::getRoutes()->getByName('password.update')->gatherMiddleware());
    }
    public function test_customer_login_rotates_session_and_password_reset_is_non_enumerating():void {
        $user=User::factory()->create(['email'=>'login@example.com','password'=>Hash::make('CorrectPass1')]);
        $oldSessionId=$this->app['session']->getId();
        $this->post('/login',['email'=>$user->email,'password'=>'CorrectPass1'])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId,$this->app['session']->getId());
        $this->post('/logout')->assertRedirect('/');
        Notification::fake();
        $this->post('/forgot-password',['email'=>'missing@example.com'])->assertRedirect()->assertSessionHas('success','If an account exists for that email, a password reset link has been sent.');
        Notification::assertNothingSent();
    }
    public function test_customer_account_resources_are_owner_authorized():void {
        $owner=User::factory()->create();
        $other=User::factory()->create();
        $order=$owner->orders()->create(['number'=>'ER-AUTH-OWNER','status'=>'completed','payment_status'=>'paid','subtotal'=>20,'shipping'=>0,'total'=>20,'currency'=>'EUR']);
        $this->actingAs($other)->get(route('account.invoice',$order))->assertForbidden();
        $this->actingAs($other)->post(route('account.return.store',$order),['type'=>'return','reason'=>'Not mine'])->assertForbidden();
        $this->actingAs($owner)->get('/account/orders')->assertOk()->assertSee('ER-AUTH-OWNER');
    }
    public function test_returns_are_status_eligible_deduplicated_and_audited():void {
        $owner=User::factory()->create();
        $order=$owner->orders()->create(['number'=>'ER-RETURN-001','status'=>'completed','payment_status'=>'paid','subtotal'=>20,'shipping'=>0,'total'=>20,'currency'=>'EUR']);
        $this->actingAs($owner)->post(route('account.return.store',$order),['type'=>'exchange','reason'=>'Size change','details'=>'Please exchange for the next size.'])->assertRedirect()->assertSessionHas('success','Return/exchange request submitted.');
        $return=ReturnRequest::firstOrFail();
        $this->assertSame('exchange',$return->type);
        $this->assertSame('requested',$return->status);
        $this->assertDatabaseHas('audit_logs',['action'=>'customer.return_requested','subject_id'=>(string)$return->id]);
        $this->post(route('account.return.store',$order),['type'=>'return','reason'=>'Duplicate request'])->assertRedirect()->assertSessionHasErrors('order');
        $this->assertDatabaseCount('returns',1);
        $pending=$owner->orders()->create(['number'=>'ER-RETURN-002','status'=>'processing','payment_status'=>'pending','subtotal'=>10,'shipping'=>0,'total'=>10,'currency'=>'EUR']);
        $this->post(route('account.return.store',$pending),['type'=>'return','reason'=>'Too soon'])->assertRedirect()->assertSessionHasErrors('order');
        $this->get('/account/returns')->assertOk()->assertSee(['RET-','Exchange','Requested'],false);
    }
    public function test_customer_payment_ledger_and_invoice_are_owner_scoped():void {
        $owner=User::factory()->create();
        $order=$owner->orders()->create(['number'=>'ER-INVOICE-001','status'=>'shipped','payment_status'=>'paid','subtotal'=>40,'shipping'=>5,'total'=>45,'currency'=>'EUR','currency_code'=>'EUR','exchange_rate'=>1,'email'=>$owner->email,'shipping_address'=>['name'=>'Customer','line1'=>'1 Test Street','city'=>'Limerick','postcode'=>'V94 TEST','country'=>'IE']]);
        PaymentTransaction::create(['order_id'=>$order->id,'provider'=>'bank_transfer','amount'=>45,'currency'=>'EUR','status'=>'paid']);
        $this->actingAs($owner)->get('/account/payments')->assertOk()->assertSee(['Payment ledger','ER-INVOICE-001','Bank Transfer','Paid','€45.00'],false);
        $this->get(route('account.invoice',$order))->assertOk()->assertSee(['Delivery address','Payment ledger','1 Test Street','Bank Transfer'],false);
    }
    public function test_admin_order_lifecycle_records_payment_and_audit_entries():void {
        $admin=User::factory()->create(['is_admin'=>true]);
        $user=User::factory()->create();
        $order=$user->orders()->create(['number'=>'ER-ADMIN-001','order_type'=>'online','status'=>'processing','payment_status'=>'pending','payment_method'=>'bank_transfer','subtotal'=>30,'shipping'=>0,'total'=>30,'currency'=>'EUR']);
        PaymentTransaction::create(['order_id'=>$order->id,'provider'=>'bank_transfer','amount'=>30,'currency'=>'EUR','status'=>'awaiting_payment']);
        $this->actingAs($admin)->patch(route('admin.order-master.update',['online',$order]),['status'=>'shipped','payment_status'=>'paid'])->assertRedirect()->assertSessionHas('success','Order status updated and recorded.');
        $this->assertSame('shipped',$order->fresh()->status);
        $this->assertSame('paid',$order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions',['order_id'=>$order->id,'status'=>'paid','provider'=>'bank_transfer']);
        $this->assertDatabaseHas('audit_logs',['action'=>'admin.order_updated','subject_id'=>(string)$order->id]);
        $this->actingAs($admin)->get('/admin/orders/online')->assertOk()->assertSee(['ER-ADMIN-001','paid'],false);
    }
    public function test_order_masters_have_isolated_filters_metrics_detail_and_invoice():void {
        $admin=User::factory()->create(['is_admin'=>true]);
        $onlineUser=User::factory()->create();
        $corporateUser=User::factory()->create();
        $online=$onlineUser->orders()->create(['number'=>'ER-MASTER-ONLINE','order_type'=>'online','status'=>'processing','payment_status'=>'paid','subtotal'=>30,'shipping'=>0,'discount'=>0,'total'=>30,'currency'=>'EUR','currency_code'=>'EUR','exchange_rate'=>1,'email'=>$onlineUser->email,'shipping_address'=>['name'=>'Online Customer','line1'=>'1 Test Street','city'=>'Limerick','country'=>'IE']]);
        $product=Product::create(['name'=>'Master Cap','slug'=>'master-cap','sku'=>'MASTER-001','price'=>30,'stock'=>5,'is_active'=>true]);
        $online->items()->create(['product_id'=>$product->id,'name'=>'Master Cap','sku'=>$product->sku,'quantity'=>1,'unit_price'=>30,'total'=>30]);
        PaymentTransaction::create(['order_id'=>$online->id,'provider'=>'card','amount'=>30,'currency'=>'EUR','status'=>'paid']);
        $corporate=$corporateUser->orders()->create(['number'=>'ER-MASTER-CORPORATE','order_type'=>'corporate','status'=>'processing','payment_status'=>'pending','subtotal'=>45,'shipping'=>0,'discount'=>0,'total'=>45,'currency'=>'EUR','currency_code'=>'EUR','exchange_rate'=>1,'email'=>$corporateUser->email]);
        $this->actingAs($admin)->get('/admin/orders/online?payment_status=paid')->assertOk()->assertSee(['Online Orders','Paid revenue','€30.00','ER-MASTER-ONLINE'],false)->assertDontSee('ER-MASTER-CORPORATE');
        $this->actingAs($admin)->get(route('admin.order-master.show',['online',$online]))->assertOk()->assertSee(['Order items','Payment ledger','Master Cap'],false);
        $this->actingAs($admin)->get(route('admin.order-master.invoice',['online',$online]))->assertOk()->assertSee(['Invoice ER-MASTER-ONLINE','Payment ledger'],false);
        $this->actingAs($admin)->get(route('admin.order-master.show',['corporate',$online]))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.order-master.invoice',['corporate',$online]))->assertNotFound();
        $this->actingAs($admin)->get('/admin/orders/corporate?status=cancelled')->assertOk()->assertDontSee('ER-MASTER-ONLINE')->assertSee('No orders in this master',false);
        $this->assertSame('online',$online->fresh()->order_type);
        $this->assertSame('corporate',$corporate->fresh()->order_type);
    }
    public function test_checkout_uses_saved_address_shipping_discount_payment_and_clears_cart():void {
        $user=User::factory()->create(['email'=>'checkout@example.com']);
        $product=Product::create(['name'=>'Checkout Cap','slug'=>'checkout-cap','sku'=>'CHK-001','price'=>30,'stock'=>5,'is_active'=>true]);
        $address=Address::create(['user_id'=>$user->id,'label'=>'Work','name'=>'Aoife Customer','phone'=>'0890000000','line1'=>'1 Test Street','city'=>'Limerick','county'=>'Limerick','postcode'=>'V94 TEST','country'=>'IE','is_default'=>true]);
        $shipping=ShippingMethod::create(['name'=>'Ireland Standard','code'=>'IE_STANDARD_TEST','price'=>6.95,'free_over'=>100,'is_active'=>true]);
        Discount::create(['code'=>'WELCOME10_TEST','type'=>'percent','value'=>10,'minimum_order'=>50,'is_active'=>true]);
        $this->actingAs($user)->post(route('cart.add',$product),['quantity'=>2])->assertRedirect('/cart');
        $this->get('/checkout')->assertOk()->assertSee(['Use a saved address','Ireland Standard'],false);
        $response=$this->post('/checkout',['address_id'=>$address->id,'email'=>$user->email,'shipping_method'=>$shipping->code,'payment_method'=>'bank_transfer','discount_code'=>'WELCOME10_TEST']);
        $order=Order::where('user_id',$user->id)->latest()->firstOrFail();
        $response->assertRedirect(route('order.success',$order));
        $this->assertEquals(60.00,(float)$order->subtotal);
        $this->assertEquals(6.95,(float)$order->shipping);
        $this->assertEquals(6.00,(float)$order->discount);
        $this->assertEquals(60.95,(float)$order->total);
        $this->assertSame('bank_transfer',$order->payment_method);
        $this->assertSame('pending',$order->payment_status);
        $this->assertSame('EUR',$order->currency_code);
        $this->assertEquals(1,(float)$order->exchange_rate);
        $this->assertSame('1 Test Street',$order->shipping_address['line1']);
        $this->assertSame(2,$order->items()->firstOrFail()->quantity);
        $this->assertSame(3,(int)$product->fresh()->stock);
        $this->assertSame('bank_transfer',PaymentTransaction::where('order_id',$order->id)->value('provider'));
        $movement=InventoryMovement::where('reference',$order->number)->firstOrFail();
        $this->assertSame(-2,$movement->quantity);
        $this->assertSame($product->id,$movement->product_id);
        $this->assertEmpty($this->app['session']->get('cart',[]));
        $this->get(route('order.success',$order))->assertOk()->assertSee(['Order confirmed','€60.95'],false);
    }
    public function test_checkout_rejects_invalid_discount_and_empty_cart_with_explicit_failures():void {
        $user=User::factory()->create();
        $product=Product::create(['name'=>'Failure Cap','slug'=>'failure-cap','sku'=>'FAIL-001','price'=>20,'stock'=>2,'is_active'=>true]);
        $this->actingAs($user)->post(route('cart.add',$product),['quantity'=>1])->assertRedirect('/cart');
        $this->post('/checkout',['name'=>'Customer','email'=>$user->email,'line1'=>'1 Test Street','city'=>'Limerick','country'=>'IE','payment_method'=>'cod','discount_code'=>'DOES_NOT_EXIST'])->assertRedirect()->assertSessionHasErrors('discount_code');
        $this->assertDatabaseCount('orders',0);
        $this->assertNotEmpty($this->app['session']->get('cart',[]));
        $this->post('/checkout',['name'=>'Customer','email'=>$user->email,'line1'=>'1 Test Street','city'=>'Limerick','country'=>'IE','payment_method'=>'cod','shipping_method'=>'NOT_ACTIVE'])->assertRedirect()->assertSessionHasErrors('shipping_method');
        $this->actingAs($user)->delete(route('cart.remove',array_key_first($this->app['session']->get('cart'))))->assertRedirect('/cart');
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('cart');
    }
    public function test_checkout_rechecks_locked_stock_and_does_not_oversell_stale_cart():void {
        $user=User::factory()->create();
        $product=Product::create(['name'=>'Stale Cap','slug'=>'stale-cap','sku'=>'STALE-001','price'=>25,'stock'=>1,'is_active'=>true]);
        $this->actingAs($user)->post(route('cart.add',$product),['quantity'=>1])->assertRedirect('/cart');
        $product->update(['stock'=>0]);
        $this->post('/checkout',['name'=>'Customer','email'=>$user->email,'line1'=>'1 Test Street','city'=>'Limerick','country'=>'IE','payment_method'=>'cod'])->assertRedirect()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders',0);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->assertSame(1,$this->app['session']->get('cart')[$product->id.':0:'.md5(json_encode([]))]['quantity']);
    }
    public function test_external_services_default_to_disabled():void {foreach(config('external') as $service)$this->assertFalse($service['enabled']);}
    public function test_admin_has_all_six_order_masters():void {$admin=User::factory()->create(['is_admin'=>true]);foreach(['online','corporate','bulk','franchise','franchise_retail','buyer'] as $type)$this->actingAs($admin)->get('/admin/orders/'.$type)->assertOk();}
    public function test_invalid_order_master_is_not_routable():void {$admin=User::factory()->create(['is_admin'=>true]);$this->actingAs($admin)->get('/admin/orders/invalid')->assertNotFound();}
    public function test_page_manager_renders_for_admin():void {$admin=User::factory()->create(['is_admin'=>true]);$this->actingAs($admin)->get('/admin/pages')->assertOk()->assertSee('Page Manager');}
    public function test_excluded_modules_are_not_routable():void {$admin=User::factory()->create(['is_admin'=>true]);foreach(['production','finance','payroll','pos','hr'] as $module)$this->actingAs($admin)->get('/admin/resource/'.$module)->assertNotFound();}
    public function test_cart_add_update_remove_contract_is_consistent():void {$product=Product::create(['name'=>'Test Cap','slug'=>'test-cap','sku'=>'TEST-001','price'=>34.99,'stock'=>10,'is_active'=>true]);$this->post('/cart/'.$product->id,['quantity'=>2])->assertRedirect('/cart')->assertSessionHas('cart');$key=array_key_first($this->app['session']->get('cart'));$this->patch('/cart/'.$key,['quantity'=>3])->assertRedirect('/cart');$this->assertSame(3,$this->app['session']->get('cart')[$key]['quantity']);$this->delete('/cart/'.$key)->assertRedirect('/cart');$this->assertEmpty($this->app['session']->get('cart',[]));}
    public function test_tenant_scope_can_isolate_company_records():void {$first=Company::create(['name'=>'Company One','code'=>'C1']);$second=Company::create(['name'=>'Company Two','code'=>'C2']);Category::create(['company_id'=>$first->id,'name'=>'One','slug'=>'one']);Category::create(['company_id'=>$second->id,'name'=>'Two','slug'=>'two']);$this->assertSame(['one'],Category::forCompany($first->id)->pluck('slug')->all());$this->assertSame(['two'],Category::forCompany($second->id)->pluck('slug')->all());}
}
