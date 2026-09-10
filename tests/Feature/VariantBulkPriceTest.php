<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class VariantBulkPriceTest extends TestCase
{
use RefreshDatabase;
public function test_admin_can_bulk_update_variant_prices():void{$admin=User::factory()->create(['is_admin'=>true]);$product=Product::create(['name'=>'Price Test Cap','slug'=>'price-test-cap','sku'=>'PRICE-CAP-001','description'=>'Price test product.','price'=>30,'stock'=>20,'is_active'=>true,'status'=>'active']);$variant=ProductVariant::create(['product_id'=>$product->id,'sku'=>'PRICE-CAP-001-GRN','price'=>30,'stock'=>5,'status'=>'active','is_active'=>true]);$this->actingAs($admin)->post(route('admin.variants.bulk-price'),['variants'=>[$variant->public_uuid],'price'=>27.456,'compare_price'=>35.991])->assertRedirect();$variant->refresh();$this->assertSame('27.46',$variant->price);$this->assertSame('35.99',$variant->compare_price);}
}
