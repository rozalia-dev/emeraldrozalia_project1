<?php

namespace App\Services;

use App\Models\ContentPage;

/**
 * The starter canvas for an existing public page.
 *
 * Homepage composition is migrated separately because it has its own
 * reference-faithful renderer. The remaining public pages use the same
 * controlled section vocabulary as the Page Builder and receive these
 * starter blocks when their legacy record has no persisted sections yet.
 */
final class PageSectionBlueprints
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forPage(ContentPage $page): array
    {
        if ($page->isHomepage()) {
            return [];
        }

        return $this->forSlug((string) $page->slug, (string) ($page->title ?: 'Public page'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forSlug(string $slug, string $title = 'Public page'): array
    {
        $title = trim($title) ?: 'Public page';

        return match ($slug) {
            'contact' => [
                $this->hero('Contact Us', 'We are here to help.', 'Have a question about hats, caps, an order or a partnership opportunity? Our team in Limerick is ready to assist you.'),
                $this->content('Contact details', 'Reach Emerald Rozalia through the approved contact channels shown on the public Contact Us page.'),
                $this->form('Send us a message', 'contact'),
                $this->cta('Arrange a conversation', 'Choose a convenient time to speak with our team.', '/contact#contact-schedule'),
            ],
            'corporate-orders' => [
                $this->hero('Corporate hero', 'Corporate Orders', 'Premium headwear for teams, events, promotions and corporate gifting, made in Limerick.'),
                $this->content('Embroidered Caps', 'Premium embroidery for a lasting impression.'),
                $this->content('Printed Caps', 'High quality print for bold branding.'),
                $this->content('Custom Designs', 'Bespoke styles to match your brand identity.'),
                $this->content('Premium Quality', 'Durable materials and exceptional comfort.'),
                $this->gallery('Trusted Organisations'),
                $this->form('Request a corporate quote', 'corporate-orders'),
                $this->cta('Talk to our team', 'We will route your request through the Communication Centre.', '/corporate-orders#corporate-quote'),
            ],
            'bulk-orders' => [
                $this->hero('Bulk Orders', 'Made for your next order.', 'Reliable Irish-made hats and caps for clubs, teams, businesses and events.'),
                $this->content('Bulk order planning', 'Tell us what you need and our team will help shape the right product and quantity.'),
                $this->form('Request a bulk quote', 'bulk-orders'),
                $this->cta('Start your bulk order', 'Share your requirements with Emerald Rozalia.', '/bulk-orders'),
            ],
            'franchise' => [
                $this->hero('Franchise Apply', 'Franchise open now for Ireland.', 'Build a retail business with an Irish headwear brand and an experienced Limerick-based team.'),
                $this->content('Franchise opportunity', 'Explore the territory, store and support information before sending an application.'),
                $this->form('Franchise enquiry', 'franchise'),
                $this->cta('Become a store owner', 'Start the conversation about your own Emerald Rozalia retail store.', '/franchise'),
            ],
            'careers' => [
                $this->hero('Hiring Apply', 'Build your career with us.', 'Join a Limerick-based Irish manufacturer creating premium hats and caps for customers everywhere.'),
                $this->content('Work with Emerald Rozalia', 'Share your experience and the type of opportunity you are looking for.'),
                $this->form('Apply now', 'careers'),
                $this->cta('Meet the team', 'Learn more about how we work in Limerick.', '/factory'),
            ],
            'global-network' => [
                $this->hero('Our Global Network', 'Connected from Limerick.', 'Present verified distributors, retail partners and territories from our Irish headquarters.'),
                $this->content('Irish roots. Global reach.', 'The public network canvas is ready for approved territory and partner content.'),
                $this->cta('Contact our team', 'Ask about a territory or partnership opportunity.', '/contact'),
            ],
            'factory', 'how-we-work' => [
                $this->hero('How We Work', 'Inside our factory.', 'Follow the craft, quality and finishing steps behind Emerald Rozalia headwear.'),
                $this->content('Our manufacturing story', 'From design and pattern engineering to finishing, inspection and packing, our Limerick team works with care.'),
                $this->gallery('Factory story gallery'),
                $this->cta('Welcome to visit our factory', 'Arrange a visit with our team in Limerick.', '/contact#contact-schedule'),
            ],
            'virtual-tryon' => [
                $this->hero('Virtual Try-On', 'See the fit before you choose.', 'Upload a photo and preview selected Emerald Rozalia hats in the virtual studio.'),
                $this->content('Virtual studio guidance', 'Use approved product overlays and keep your photo private in the browser session.'),
                $this->cta('Open the studio', 'Try an Emerald Rozalia hat from the product catalogue.', '/virtual-tryon'),
            ],
            'irish-traditional' => [
                $this->hero('Irish Traditional Hats', 'Timeless Irish flat caps.', 'Authentic flat caps crafted from premium tweed in Limerick, Ireland.'),
                $this->gallery('Irish traditional collection'),
                $this->cta('Explore the collection', 'Discover Irish-made heritage styles.', '/irish-traditional'),
            ],
            'irish-heritage' => [
                $this->hero('Irish Heritage Collection', 'Tradition, made in Limerick.', 'Classic hats with timeless Irish character, crafted with care using premium materials.'),
                $this->gallery('Irish heritage collection'),
                $this->cta('Explore heritage hats', 'View the latest approved products in this collection.', '/irish-heritage'),
            ],
            'collections' => [
                $this->hero('Our Collections', 'Find your Emerald Rozalia edit.', 'Explore considered collections of Irish-made hats and caps.'),
                $this->gallery('Featured collections'),
                $this->cta('Shop all collections', 'Browse the full product catalogue.', '/collections'),
            ],
            'new-arrivals' => [
                $this->hero('New Arrivals', 'Fresh styles from Limerick.', 'Discover the latest Emerald Rozalia hats and caps.'),
                $this->gallery('Latest arrivals'),
                $this->cta('Shop new arrivals', 'See every newly released product.', '/new-arrivals'),
            ],
            default => [
                $this->hero($title, 'Emerald Rozalia public page.', 'Timeless styles, Irish heritage and premium headwear made in Limerick.'),
                $this->content('Page content', 'Add the approved editorial content for this public page.'),
            ],
        };
    }

    /** @return array<string, mixed> */
    private function hero(string $label, string $title, string $content): array
    {
        return $this->section('hero', $label, [
            'title' => $title,
            'content' => $content,
            'button_label' => 'Explore more',
            'url' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function content(string $label, string $content): array
    {
        return $this->section('content', $label, [
            'title' => $label,
            'content' => $content,
        ]);
    }

    /** @return array<string, mixed> */
    private function gallery(string $label): array
    {
        return $this->section('gallery', $label, [
            'title' => $label,
            'content' => 'Select approved media to populate this gallery.',
            'items' => [],
        ]);
    }

    /** @return array<string, mixed> */
    private function cta(string $label, string $content, string $url): array
    {
        return $this->section('cta', $label, [
            'title' => $label,
            'content' => $content,
            'button_label' => 'Learn more',
            'url' => $url,
        ]);
    }

    /** @return array<string, mixed> */
    private function form(string $label, string $type): array
    {
        return $this->section('form', $label, [
            'title' => $label,
            'content' => 'Your enquiry will be routed through the Communication Centre for assignment and follow-up.',
            'inquiry_type' => $type,
            'button_label' => 'Submit enquiry',
        ]);
    }

    /** @return array<string, mixed> */
    private function section(string $type, string $label, array $settings): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'sort_order' => 0,
            'region' => 'main',
            'locale' => 'en',
            'media_uuid' => null,
            'focal_point' => null,
            'devices' => ['desktop', 'tablet', 'mobile'],
            'variant' => null,
            'animation' => 'none',
            'analytics_key' => null,
            'validation_errors' => null,
            'settings' => $settings,
            'visible' => true,
        ];
    }
}
