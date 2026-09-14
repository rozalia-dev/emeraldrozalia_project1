<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFilterContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalogue_filters_use_decimal_contracts_and_reject_exponents(): void
    {
        $inside = Product::create([
            'name' => 'Exact Price Cap',
            'slug' => 'exact-price-cap',
            'sku' => 'EXACT-PRICE-001',
            'price' => '30.10',
            'stock' => 2,
            'is_active' => true,
        ]);
        Product::create([
            'name' => 'Outside Price Cap',
            'slug' => 'outside-price-cap',
            'sku' => 'OUTSIDE-PRICE-001',
            'price' => '40.21',
            'stock' => 2,
            'is_active' => true,
        ]);

        $this->get('/shop?min_price=30.10&max_price=40.20')
            ->assertOk()
            ->assertSee('Exact Price Cap', false)
            ->assertDontSee('Outside Price Cap', false);

        $this->get('/shop?min_price=1e2')
            ->assertRedirect()
            ->assertSessionHasErrors('min_price');

        $this->assertNotNull($inside->id);
    }
}
