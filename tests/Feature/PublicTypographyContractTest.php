<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTypographyContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_shared_public_route_uses_the_theme_typography_contract(): void
    {
        foreach ([
            'home', 'shop', 'collections', 'new.arrivals', 'corporate.orders', 'bulk.orders',
            'franchise', 'careers', 'global.network', 'contact', 'virtual-tryon',
            'irish.traditional', 'irish.heritage',
        ] as $routeName) {
            $response = $this->get(route($routeName))->assertOk();

            $response->assertSee('class="site-body', false)
                ->assertSee('/css/theme-runtime.css?v=20260913-batch17-typography', false)
                ->assertSee('--site-base-size:', false)
                ->assertSee('--site-font-family:', false);
        }
    }

    public function test_factory_reference_route_uses_the_same_base_typography_contract(): void
    {
        $this->get(route('factory'))->assertOk()
            ->assertSee('class="factory-reference-body', false)
            ->assertSee('/css/theme-runtime.css?v=20260913-batch17-typography', false)
            ->assertSee('--site-base-size:', false)
            ->assertSee('--site-font-family:', false);
    }
}
