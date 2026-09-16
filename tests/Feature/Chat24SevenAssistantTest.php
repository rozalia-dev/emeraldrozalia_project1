<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Services\Chat24SevenAssistant;
use App\Services\CommunicationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Chat24SevenAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_answers_only_from_the_selected_company_product_facts(): void
    {
        [$company, $product] = $this->publishedProduct([
            'material' => '100% Irish wool',
            'sizes' => ['S', 'M', 'L'],
            'price' => 49.95,
            'stock' => 24,
            'product_metadata' => [
                'published_website' => true,
                'currency' => 'EUR',
                'minimum_order_quantity' => 100,
            ],
        ]);

        [$otherCompany] = $this->publishedProduct([
            'name' => 'Other Tenant Secret Cap',
            'slug' => 'other-tenant-secret-cap',
            'sku' => 'OTHER-SECRET-001',
            'material' => 'Secret fibre',
            'price' => 999.99,
        ], 'Other Company', 'OTHER');

        $assistant = app(Chat24SevenAssistant::class);

        $fabric = $assistant->answer('What kind of fabric?', $product->slug, $company->id);
        $this->assertStringContainsString('100% Irish wool', $fabric['body']);
        $this->assertFalse($fabric['requires_human']);

        $size = $assistant->answer('What sizes are available?', $product->slug, $company->id);
        $this->assertStringContainsString('S, M, L', $size['body']);

        $price = $assistant->answer('What is the price?', $product->slug, $company->id);
        $this->assertStringContainsString('€49.95', $price['body']);

        $moq = $assistant->answer('What is the minimum order quantity?', $product->slug, $company->id);
        $this->assertStringContainsString('100 piece', $moq['body']);

        $crossTenant = $assistant->answer('Other Tenant Secret Cap price?', null, $company->id);
        $this->assertStringNotContainsString('999.99', $crossTenant['body']);
        $this->assertStringNotContainsString('Secret fibre', $crossTenant['body']);
        $this->assertNotSame($company->id, $otherCompany->id);
    }

    public function test_missing_moq_is_escalated_instead_of_invented(): void
    {
        [$company, $product] = $this->publishedProduct([
            'product_metadata' => ['published_website' => true, 'currency' => 'EUR'],
        ]);

        $reply = app(Chat24SevenAssistant::class)
            ->answer('What is the MOQ?', $product->slug, $company->id);

        $this->assertTrue($reply['requires_human']);
        $this->assertStringContainsString('no confirmed MOQ', $reply['body']);
        $this->assertStringContainsString('won’t invent', $reply['body']);
    }

    public function test_public_chat_stores_two_way_messages_and_is_session_owned(): void
    {
        [$company, $product] = $this->publishedProduct([
            'material' => 'Organic cotton',
            'price' => 29.90,
            'product_metadata' => [
                'published_website' => true,
                'currency' => 'EUR',
                'minimum_order_quantity' => 50,
            ],
        ]);

        $start = $this->withSession(['company_id' => $company->id])
            ->postJson(route('chat24.start'), ['context_product_slug' => $product->slug])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0.actor', 'ai_assistant');

        $uuid = (string) $start->json('conversation_uuid');

        $this->postJson(route('chat24.send', ['conversation' => $uuid]), [
            'message' => 'What kind of fabric?',
            'context_product_slug' => $product->slug,
        ])->assertOk()
            ->assertJsonPath('messages.1.actor', 'ai_assistant')
            ->assertJsonPath('human_requested', false)
            ->assertJsonFragment(['body' => $product->name.' is recorded with material: Organic cotton.']);

        $conversation = Conversation::withoutGlobalScopes()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame($company->id, (int) $conversation->company_id);
        $this->assertSame('chat', $conversation->channel);
        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame(1, $conversation->messages()->where('direction', 'inbound')->count());
        $this->assertSame(2, $conversation->messages()->where('direction', 'outbound')->count());

        $this->withSession(['company_id' => $company->id, 'chat24_conversations' => []])
            ->getJson(route('chat24.messages', ['conversation' => $uuid]))
            ->assertNotFound();
    }

    public function test_human_reply_pauses_ai_and_unassigning_resumes_it(): void
    {
        Queue::fake();
        [$company] = $this->publishedProduct();
        $admin = User::factory()->create(['is_admin' => true]);
        $company->users()->attach($admin->id, ['role' => 'admin', 'is_default' => true]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => 'chat',
            'contact' => 'Website visitor test',
            'subject' => 'Website Chat 24/7',
            'status' => 'pending',
            'priority' => 'high',
            'metadata' => [
                'source' => 'website_chat_24_7',
                'ai_paused' => false,
                'human_requested' => true,
            ],
        ]);

        $this->actingAs($admin);
        session(['company_id' => $company->id]);

        $communication = app(CommunicationCenter::class);
        $communication->sendReply($conversation, 'A human agent is handling this now.', 'chat-human-takeover');

        $conversation->refresh();
        $this->assertSame($admin->id, (int) $conversation->assigned_to);
        $this->assertTrue((bool) data_get($conversation->metadata, 'ai_paused'));
        $this->assertFalse((bool) data_get($conversation->metadata, 'human_requested'));

        $communication->updateConversation($conversation, ['assigned_to' => null]);
        $conversation->refresh();

        $this->assertNull($conversation->assigned_to);
        $this->assertFalse((bool) data_get($conversation->metadata, 'ai_paused'));
        $this->assertFalse((bool) data_get($conversation->metadata, 'human_requested'));
    }

    public function test_uefa_and_fifa_queries_never_imply_unverified_official_licensing(): void
    {
        [$company] = $this->publishedProduct();
        $assistant = app(Chat24SevenAssistant::class);

        foreach (['UEFA hats', 'FIFA caps'] as $query) {
            $reply = $assistant->answer($query, null, $company->id);
            $this->assertStringContainsString('will not describe any item as officially licensed', $reply['body']);
        }
    }

    private function publishedProduct(array $overrides = [], string $companyName = 'Emerald Chat Tenant', string $companyCode = 'CHAT'): array
    {
        $company = Company::create([
            'name' => $companyName,
            'code' => $companyCode.'-'.str()->upper(str()->random(5)),
            'active' => true,
        ]);
        $category = Category::create([
            'company_id' => $company->id,
            'name' => 'Chat Hats',
            'slug' => 'chat-hats-'.str()->lower(str()->random(5)),
            'description' => 'Chat assistant test category.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $defaults = [
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Emerald Heritage Cap',
            'slug' => 'emerald-heritage-cap-'.str()->lower(str()->random(5)),
            'sku' => 'CHAT-'.str()->upper(str()->random(8)),
            'description' => 'Irish Heritage premium cap for product chat testing.',
            'material' => 'Wool blend',
            'sizes' => ['M', 'L'],
            'price' => 39.90,
            'stock' => 12,
            'is_new' => true,
            'is_active' => true,
            'status' => 'active',
            'published_at' => now()->subMinute(),
            'product_metadata' => [
                'published_website' => true,
                'currency' => 'EUR',
                'minimum_order_quantity' => 25,
            ],
        ];

        $product = Product::create(array_replace($defaults, $overrides));

        return [$company, $product];
    }
}
