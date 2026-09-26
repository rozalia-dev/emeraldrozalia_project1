<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EmailMailboxController;
use App\Jobs\DeliverCommunicationMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmailMailboxDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_mailbox_keeps_communication_center_route_name_after_name_lookup_refresh(): void
    {
        app('router')->getRoutes()->refreshNameLookups();

        $this->assertTrue(Route::has('admin.communication-center.page.email'));

        $route = app('router')->getRoutes()->getByName('admin.communication-center.page.email');
        $this->assertNotNull($route);
        $this->assertSame('admin/resource/email', $route->uri());
        $this->assertSame(EmailMailboxController::class.'@index', $route->getActionName());
    }

    public function test_admin_email_dashboard_exposes_mailbox_folders_and_mail_setup(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/resource/email')
            ->assertOk()
            ->assertSee('Email Dashboard')
            ->assertSee('Inbox')
            ->assertSee('Sent')
            ->assertSee('Drafts')
            ->assertSee('Trash')
            ->assertSee('Email Logs')
            ->assertSee('Mail Setup');
    }

    public function test_compose_email_persists_message_and_queues_delivery(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.email-mailbox.compose'), [
            'to' => 'customer@example.test',
            'subject' => 'Order update',
            'body' => 'Your order is ready.',
            'action' => 'send',
        ]);

        $conversation = Conversation::query()->where('channel', 'email')->where('contact', 'customer@example.test')->firstOrFail();
        $message = ConversationMessage::query()->where('conversation_id', $conversation->id)->firstOrFail();

        $response->assertRedirect('/admin/resource/email?folder=sent&conversation='.$conversation->uuid);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('queued', $message->delivery_status);
        Queue::assertPushed(DeliverCommunicationMessage::class, fn ($job) => $job->messageId === $message->id);
    }

    public function test_email_mailbox_supports_private_attachments_thread_download_and_print_controls(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.email-mailbox.compose'), [
            'to' => 'customer@example.test',
            'subject' => 'Order documents',
            'body' => 'The requested document is attached.',
            'action' => 'send',
            'attachments' => [UploadedFile::fake()->create('order-details.pdf', 24, 'application/pdf')],
        ])->assertRedirect();

        $conversation = Conversation::query()->where('channel', 'email')->where('contact', 'customer@example.test')->firstOrFail();
        $message = ConversationMessage::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $attachments = (array) data_get($message->payload, 'attachments', []);
        $this->assertCount(1, $attachments);
        $attachment = $attachments[0];
        Storage::disk('local')->assertExists($attachment['path']);

        $this->actingAs($admin)
            ->get(route('admin.email-mailbox.attachment.download', [
                'conversationUuid' => $conversation->uuid,
                'messageUuid' => $message->uuid,
                'attachmentId' => $attachment['id'],
            ]))
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->get(route('admin.email-mailbox.download', ['conversationUuid' => $conversation->uuid]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('The requested document is attached.');

        $pdf = $this->get(route('admin.email-mailbox.download', ['conversationUuid' => $conversation->uuid, 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="email-thread-'.substr($conversation->uuid, 0, 8).'.pdf"')
            ->assertSee('%PDF-1.4', false);
        $pdfContent = $pdf->getContent();
        $this->assertMatchesRegularExpression('/startxref\n(\d+)\n%%EOF$/', $pdfContent);
        preg_match('/startxref\n(\d+)\n%%EOF$/', $pdfContent, $pdfXrefMatch);
        $this->assertSame('xref', substr($pdfContent, (int) $pdfXrefMatch[1], 4));

        $this->get(route('admin.email-mailbox.download', ['conversationUuid' => $conversation->uuid, 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="email-thread-'.substr($conversation->uuid, 0, 8).'.csv"')
            ->assertSee('Subject,Contact,"Conversation ID"', false)
            ->assertSee('The requested document is attached.', false);

        $word = $this->get(route('admin.email-mailbox.download', ['conversationUuid' => $conversation->uuid, 'format' => 'docx']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('Content-Disposition', 'attachment; filename="email-thread-'.substr($conversation->uuid, 0, 8).'.docx"')
            ->assertSee('PK', false);
        $wordPath = tempnam(sys_get_temp_dir(), 'email-thread-test-');
        $this->assertNotFalse($wordPath);
        try {
            file_put_contents($wordPath, $word->getContent());
            $wordArchive = new \ZipArchive();
            $this->assertNotSame(false, $wordArchive->open($wordPath));
            $documentXml = $wordArchive->getFromName('word/document.xml');
            $this->assertIsString($documentXml);
            $this->assertStringContainsString('The requested document is attached.', $documentXml);
            $wordArchive->close();
        } finally {
            @unlink($wordPath);
        }

        $this->get('/admin/resource/email?folder=sent&conversation='.$conversation->uuid)
            ->assertOk()
            ->assertSee('Print')
            ->assertSee('Download TXT')
            ->assertSee('Download PDF')
            ->assertSee('Download CSV')
            ->assertSee('Download Word (.docx)')
            ->assertSee('Attachments')
            ->assertSee('Download order-details.pdf');

        Queue::assertPushed(DeliverCommunicationMessage::class, fn ($job) => $job->messageId === $message->id);
    }

    public function test_mailbox_can_save_a_draft_without_sending_it(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.email-mailbox.compose'), [
            'to' => 'draft@example.test',
            'subject' => 'Draft subject',
            'body' => 'Not sent yet',
            'action' => 'draft',
            'attachments' => [UploadedFile::fake()->create('draft-brief.pdf', 12, 'application/pdf')],
        ])->assertRedirect();

        $draft = Conversation::query()->where('contact', 'draft@example.test')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame('Not sent yet', data_get($draft->metadata, 'draft_body'));
        $draftAttachments = (array) data_get($draft->metadata, 'draft_attachments', []);
        $this->assertCount(1, $draftAttachments);
        Storage::disk('local')->assertExists($draftAttachments[0]['path']);
        $this->assertSame(0, $draft->messages()->count());
        Queue::assertNothingPushed();

        $this->actingAs($admin)
            ->get('/admin/resource/email?folder=drafts')
            ->assertOk()
            ->assertSee('Draft subject');
    }

    public function test_signed_inbound_email_webhook_creates_an_inbox_thread(): void
    {
        config(['communication.webhook_secrets.email' => 'test-inbound-secret']);
        $payload = json_encode([
            'event_id' => 'inbound-test-001',
            'from' => 'customer@example.test',
            'to' => 'info@emeraldrozalia.com',
            'subject' => 'Customer question',
            'body' => 'Can you help with my order?',
            'message_id' => 'provider-message-1',
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'test-inbound-secret');

        $response = $this->call('POST', '/api/v1/communication/email/inbound', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_COMMUNICATION_SIGNATURE' => $signature,
        ], $payload);

        $response->assertStatus(202)->assertJson(['accepted' => true, 'duplicate' => false]);
        $conversation = Conversation::query()->where('contact', 'customer@example.test')->firstOrFail();
        $this->assertSame('email', $conversation->channel);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'delivery_status' => 'stored',
        ]);
    }
}
