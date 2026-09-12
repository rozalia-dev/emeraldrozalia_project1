<?php

namespace Tests\Feature;

use App\Models\{ContentPage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_shell_keeps_the_locked_header_and_footer_contact_rule(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $navStart = strpos($html, '<nav data-nav');
        $navEnd = $navStart === false ? false : strpos($html, '</nav>', $navStart);

        $this->assertNotFalse($navStart);
        $this->assertNotFalse($navEnd);
        $nav = substr($html, $navStart, $navEnd - $navStart);
        $labels = ['HOME', 'SHOP', 'COLLECTIONS', 'NEW ARRIVALS', 'CORPORATE ORDER', 'BULK ORDER', 'FRANCHISE APPLY', 'HIRING APPLY'];
        $offset = -1;

        foreach ($labels as $label) {
            $position = strpos($nav, $label);
            $this->assertNotFalse($position, "Missing canonical navigation item: {$label}");
            $this->assertGreaterThan($offset, $position, "Navigation item is out of order: {$label}");
            $offset = $position;
        }

        $this->assertStringNotContainsString('CONTACT US', $nav);
        $this->assertStringContainsString('href="' . route('contact') . '"', $html);
        $this->assertStringContainsString('aria-label="Language"', $html);
        $this->assertStringContainsString('aria-label="Currency"', $html);
        $this->assertStringContainsString('aria-label="Search"', $html);
        $this->assertStringContainsString('aria-label="Login"', $html);
        $this->assertStringContainsString('aria-label="Cart"', $html);
    }

    public function test_published_footer_pages_are_available_without_expanding_the_fixed_header(): void
    {
        $page = ContentPage::create([
            'title' => 'Responsible Sourcing',
            'slug' => 'responsible-sourcing',
            'intro' => 'Our sourcing principles.',
            'body' => 'A footer-managed page.',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'navigation_visible' => true,
            'published_at' => now(),
            'meta' => ['settings' => ['visibility' => 'public', 'show_in_footer' => true]],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Responsible Sourcing', $html);
        $this->assertStringContainsString('href="' . route('content.page', ['page' => $page->slug]) . '"', $html);
        $navStart = strpos($html, '<nav data-nav');
        $navEnd = strpos($html, '</nav>', $navStart);
        $this->assertStringNotContainsString('Responsible Sourcing', substr($html, $navStart, $navEnd - $navStart));
    }

    public function test_managed_sections_render_as_controlled_public_blocks(): void
    {
        $page = ContentPage::create([
            'title' => 'Limerick Stories',
            'slug' => 'limerick-stories',
            'intro' => 'Stories from the workshop.',
            'body' => 'Body copy with <script>alert(1)</script>.',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'published_at' => now(),
            'meta' => ['settings' => ['visibility' => 'public']],
        ]);
        $page->sections()->createMany([
            [
                'type' => 'hero',
                'label' => 'Made in Limerick',
                'settings' => [
                    'title' => 'Irish craft, made to last',
                    'content' => "Made with care.\nWorn everywhere.",
                    'image' => 'javascript:alert(1)',
                    'url' => 'javascript:alert(1)',
                ],
                'visible' => true,
                'sort_order' => 0,
            ],
            [
                'type' => 'gallery',
                'label' => 'Workshop gallery',
                'settings' => [
                    'items' => [
                        ['image' => 'assets/brand/home-page-reference.png', 'alt' => 'Workshop reference', 'caption' => 'A considered Irish-made edit.'],
                        ['image' => 'data:text/html,<script>alert(1)</script>', 'alt' => 'Rejected image'],
                    ],
                ],
                'visible' => true,
                'sort_order' => 1,
            ],
            [
                'type' => 'cta',
                'label' => 'Continue the conversation',
                'settings' => ['content' => 'Talk to the team.', 'button_label' => 'Contact the team', 'url' => '/contact'],
                'visible' => true,
                'sort_order' => 2,
            ],
            [
                'type' => 'form',
                'label' => 'Send an enquiry',
                'settings' => ['content' => 'We will route your request through Communication Centre.', 'inquiry_type' => 'contact'],
                'visible' => true,
                'sort_order' => 3,
            ],
        ]);

        $response = $this->get(route('page', ['page' => $page->slug]))->assertOk();

        $response->assertSee(['data-managed-section="hero"', 'Irish craft, made to last', 'Workshop gallery', 'Contact the team', 'action="' . route('inquiry') . '"', 'name="subject"', 'name="consent"'], false);
        $response->assertSee('Body copy with &lt;script&gt;alert(1)&lt;/script&gt;.', false);
        $response->assertSee('home-page-reference.png', false);
        $response->assertDontSee('javascript:alert(1)', false);
        $response->assertDontSee('data:text/html', false);
    }

    public function test_private_and_login_required_pages_are_not_publicly_exposed(): void
    {
        $private = ContentPage::create([
            'title' => 'Internal Notes',
            'slug' => 'internal-notes',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'published_at' => now(),
            'meta' => ['settings' => ['visibility' => 'private']],
        ]);
        $members = ContentPage::create([
            'title' => 'Member Resources',
            'slug' => 'member-resources',
            'body' => 'Members only content.',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'published_at' => now(),
            'meta' => ['settings' => ['visibility' => 'public', 'login_required' => true]],
        ]);

        $this->get(route('page', ['page' => $private->slug]))->assertNotFound();
        $this->get(route('page', ['page' => $members->slug]))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('page', ['page' => $members->slug]))
            ->assertOk()
            ->assertSee('Members only content.');
    }
}
