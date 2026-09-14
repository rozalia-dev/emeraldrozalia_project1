<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRouteInteractionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_route_matrix_has_shared_or_structured_shell_without_placeholder_media_links(): void
    {
        $sharedRoutes = [
            '/',
            '/shop',
            '/collections',
            '/new-arrivals',
            '/corporate-orders',
            '/bulk-orders',
            '/franchise',
            '/be-a-store-owner',
            '/quality',
            '/careers',
            '/global-network',
            '/contact',
            '/virtual-tryon',
            '/irish-traditional',
            '/irish-heritage',
            '/cart',
        ];

        foreach ($sharedRoutes as $path) {
            $response = $this->get($path)->assertOk();
            $html = $response->getContent();

            $this->assertStringContainsString('data-public-shell="shared"', $html, $path.' must use the shared public shell');
            $this->assertStringNotContainsString('href="#"', $html, $path.' must not expose placeholder links');
            $this->assertStringNotContainsString('src="/assets/', $html, $path.' must not deliver a direct public asset path');
            $this->assertStringNotContainsString('data-approved-reference', $html, $path.' must not expose reference-image metadata');
        }

        $factory = $this->get('/factory')->assertOk();
        $factoryHtml = $factory->getContent();
        $this->assertStringContainsString('data-public-media-register="factory"', $factoryHtml);
        $this->assertStringNotContainsString('src="/assets/', $factoryHtml);
        $this->assertStringNotContainsString('factory-reference-hotspot', $factoryHtml);
    }

    public function test_public_enquiry_routes_have_real_post_actions_and_csrf_tokens(): void
    {
        foreach (['/contact', '/corporate-orders', '/bulk-orders', '/franchise', '/careers'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('method="post"', false)
                ->assertSee('action="'.route('inquiry').'"', false)
                ->assertSee('name="_token"', false)
                ->assertDontSee('action="#"', false);
        }
    }

    public function test_public_media_empty_states_are_explicit_on_editorial_routes(): void
    {
        foreach (['/', '/collections', '/new-arrivals', '/corporate-orders', '/bulk-orders', '/franchise', '/factory'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('data-public-media-state="', false);
        }
    }
}
