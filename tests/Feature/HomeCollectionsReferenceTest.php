<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeCollectionsReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_approved_collections_reference_contract(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee([
                'data-home-collections-reference="approved-2026-09-08"',
                'SHOP BY COLLECTIONS',
                'THE IRISH HERITAGE',
                'Tradition, Made in Limerick.',
                'BESTSELLERS',
                '/css/home-collections.css?v=20260908-approved',
                'data-home-carousel-track',
                'data-home-carousel-prev',
                'data-home-carousel-next',
            ], false);

        $this->assertFileExists(public_path('assets/brand/home-collections-reference.webp'));
    }

    public function test_homepage_bestseller_cart_button_is_functional(): void
    {
        $product = Product::create([
            'name' => 'Emerald Reference Cap',
            'slug' => 'emerald-reference-cap',
            'sku' => 'HOME-REF-001',
            'price' => 34.99,
            'stock' => 5,
            'description' => 'Homepage bestseller reference product.',
            'is_active' => true,
            'is_new' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('cart.add', $product), false)
            ->assertSee('name="quantity" value="1"', false)
            ->assertSee('Emerald Reference Cap', false);
    }
}
