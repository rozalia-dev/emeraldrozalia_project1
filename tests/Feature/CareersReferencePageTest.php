<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareersReferencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_careers_page_renders_live_responsive_content_without_embedded_mockup(): void
    {
        $response = $this->get('/careers')
            ->assertOk()
            ->assertSee([
                'CAREER WITH US',
                'BUILD YOUR',
                'CAREER WITH',
                'EMERALD ROZALIA',
                'CRAFT.',
                'CREATE.',
                'GROW.',
                'CURRENT OPEN POSITIONS',
                'Retail Store Manager',
                'Digital Marketing Executive',
                'Warehouse &amp; Fulfilment Assistant',
                'APPLY TO JOIN EMERALD ROZALIA',
                'PROUDLY BASED',
                'IN LIMERICK',
                'READY TO BUILD YOUR FUTURE',
                '/css/careers.css?v=20260909-careers-live-layout',
                '/css/careers-fullwidth.css?v=20260910-fullwidth',
            ], false);

        $response->assertDontSee('/assets/brand/careers-reference.png', false)
            ->assertDontSee('data-approved-reference', false)
            ->assertSee('/assets/logo/logo_two_line.png', false);
    }

    public function test_career_application_enters_inquiry_and_communication_centre(): void
    {
        $response = $this->from('/careers')->post('/enquiry', [
            'type' => 'careers',
            'name' => 'Career Applicant',
            'email' => 'career.applicant@example.com',
            'phone' => '+353 89 000 1111',
            'company' => 'Digital Marketing Executive',
            'message' => 'I would like to contribute to the Emerald Rozalia digital team.',
        ]);

        $response->assertRedirect('/careers')->assertSessionHas('success');

        $inquiry = Inquiry::query()->firstOrFail();
        $conversation = Conversation::query()->with('messages')->firstOrFail();

        $this->assertSame('careers', $inquiry->type);
        $this->assertSame('Digital Marketing Executive', $inquiry->company);
        $this->assertSame('public_careers_form', $inquiry->meta['source']);
        $this->assertSame('careers', $conversation->metadata['type']);
        $this->assertSame('Digital Marketing Executive', $conversation->metadata['company']);
        $this->assertCount(1, $conversation->messages);
        $this->assertStringContainsString('digital team', $conversation->messages->first()->body);
    }
}
