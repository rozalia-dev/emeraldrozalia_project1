<?php

namespace Tests\Feature;

use App\Models\{Product, Review, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerContentContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cart_and_review_requests_use_dedicated_validation_contracts(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Customer Contract Cap',
            'slug' => 'customer-contract-cap',
            'sku' => 'CUSTOMER-CONTRACT-001',
            'price' => 25,
            'stock' => 4,
            'is_active' => true,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 0])
            ->assertRedirect()
            ->assertSessionHasErrors('quantity');
        $this->assertEmpty($this->app['session']->get('cart', []));

        $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 5,
            'title' => 'Excellent',
            'body' => 'A well-made cap.',
        ])->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);

        $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 6,
        ])->assertRedirect()->assertSessionHasErrors('rating');
        $this->assertSame(1, Review::query()->where('user_id', $user->id)->where('product_id', $product->id)->count());
    }
}
