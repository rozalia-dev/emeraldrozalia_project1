<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\FranchiseApplication;
use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchisePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_franchise_store_owner_page_matches_public_contract(): void
    {
        $this->get('/franchise')
            ->assertOk()
            ->assertSee([
                'FRANCHISE OPPORTUNITY',
                'BE A',
                'STORE OWNER',
                'CONTACT US TODAY',
                'ESTABLISHED BRAND',
                'TRAINING &amp; SUPPORT',
                'PREMIUM PRODUCTS',
                'GROW TOGETHER',
                'ENQUIRE ABOUT A FRANCHISE',
                'LET&#039;S BUILD',
                'SOMETHING GREAT',
                'SEND ENQUIRY',
                'JOIN OUR FRANCHISE NETWORK',
                '/css/franchise.css?v=20260908-store-owner',
            ], false);
    }

    public function test_franchise_enquiry_requires_message_and_consent(): void
    {
        $base = [
            'type' => 'franchise',
            'name' => 'Aoife Franchise',
            'email' => 'aoife.franchise@example.com',
            'company' => 'Limerick',
        ];

        $this->from('/franchise')
            ->post('/enquiry', $base + ['consent' => '1'])
            ->assertRedirect('/franchise')
            ->assertSessionHasErrors('message');

        $this->from('/franchise')
            ->post('/enquiry', $base + ['message' => 'I am interested in opening an Emerald Rozalia store.'])
            ->assertRedirect('/franchise')
            ->assertSessionHasErrors('consent');

        $this->assertDatabaseCount('inquiries', 0);
        $this->assertDatabaseCount('franchise_applications', 0);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_franchise_enquiry_enters_application_and_communication_centre(): void
    {
        $response = $this->from('/franchise')->post('/enquiry', [
            'type' => 'franchise',
            'name' => 'Aoife Franchise',
            'email' => 'aoife.franchise@example.com',
            'phone' => '0890000000',
            'company' => 'Limerick',
            'message' => 'I am interested in opening an Emerald Rozalia store in Limerick.',
            'consent' => '1',
        ]);

        $response->assertRedirect('/franchise')->assertSessionHas('success');

        $inquiry = Inquiry::query()->firstOrFail();
        $application = FranchiseApplication::query()->firstOrFail();
        $conversation = Conversation::query()->with('messages')->firstOrFail();

        $this->assertSame('franchise', $inquiry->type);
        $this->assertSame('public_franchise_form', $inquiry->meta['source']);
        $this->assertSame('Aoife Franchise', $application->applicant_name);
        $this->assertSame('Limerick', $application->preferred_location);
        $this->assertSame('Ireland', $application->territory);
        $this->assertSame('new', $application->status);
        $this->assertSame('high', $conversation->priority);
        $this->assertSame('franchise', $conversation->metadata['type']);
        $this->assertSame('Limerick', $conversation->metadata['company']);
        $this->assertCount(1, $conversation->messages);
        $this->assertStringContainsString('opening an Emerald Rozalia store', $conversation->messages->first()->body);
    }
}
