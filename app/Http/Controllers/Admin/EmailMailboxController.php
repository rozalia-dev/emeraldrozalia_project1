<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationTemplate;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\EmailLog;
use App\Services\CommunicationCenter;
use App\Services\CommunicationMailboxAttachmentService;
use App\Services\CommunicationTemplateAttachmentService;
use App\Services\CommunicationTemplateCatalogService;
use App\Services\CommunicationTemplateRoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class EmailMailboxController extends Controller
{
    private const FOLDERS = ['inbox', 'sent', 'drafts', 'trash', 'logs', 'setup'];

    public function __construct(
        private readonly CommunicationCenter $communication,
        private readonly CommunicationTemplateCatalogService $catalog,
        private readonly CommunicationTemplateRoleService $templateRoles,
        private readonly CommunicationTemplateAttachmentService $templateAttachments,
        private readonly CommunicationMailboxAttachmentService $mailboxAttachments,
    ) {
    }

    public function index(Request $request): View
    {
        $this->catalog->ensureForCurrentCompany();

        $folder = strtolower((string) $request->query('folder', 'inbox'));
        if (! in_array($folder, self::FOLDERS, true)) {
            $folder = 'inbox';
        }

        $counts = $this->folderCounts();
        $conversations = null;
        $selected = null;

        if (! in_array($folder, ['logs', 'setup'], true)) {
            $query = $this->folderQuery($folder);
            $this->applySearch($query, trim((string) $request->query('q', '')));
            $conversations = $query->paginate(20)->withQueryString();

            $selectedUuid = trim((string) $request->query('conversation', ''));
            if ($selectedUuid !== '') {
                $selected = $this->folderQuery($folder)
                    ->where('uuid', $selectedUuid)
                    ->with(['messages' => fn ($messages) => $messages->oldest('id'), 'assignee', 'customer'])
                    ->first();
            }
            $selected ??= $conversations->getCollection()->first();
            if ($selected && ! $selected->relationLoaded('messages')) {
                $selected->load(['messages' => fn ($messages) => $messages->oldest('id'), 'assignee', 'customer']);
            }
        }

        $emailLogs = EmailLog::query()
            ->latest('created_at')
            ->limit($folder === 'logs' ? 100 : 12)
            ->get();

        $deliveryLog = ConversationMessage::query()
            ->where('direction', 'outbound')
            ->whereHas('conversation', fn (Builder $query) => $query->where('channel', 'email'))
            ->with('conversation')
            ->latest('id')
            ->limit(25)
            ->get();

        $emailTemplates = CommunicationTemplate::query()
            ->where('channel', 'email')
            ->where('status', 'active')
            ->whereIn('id', $this->templateRoles->visibleTemplateIds($request->user()))
            ->orderBy('name')
            ->get()
            ->map(fn (CommunicationTemplate $template): array => $this->templateRoles->safeBrowserData($template))
            ->values();

        return view('admin.communication-center.email-mailbox', [
            'folder' => $folder,
            'folders' => self::FOLDERS,
            'counts' => $counts,
            'conversations' => $conversations,
            'selected' => $selected,
            'emailLogs' => $emailLogs,
            'deliveryLog' => $deliveryLog,
            'mailStatus' => $this->mailStatus(),
            'search' => trim((string) $request->query('q', '')),
            'emailTemplates' => $emailTemplates,
        ]);
    }

    public function compose(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'to' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'action' => ['nullable', 'in:send,draft'],
            'template_uuid' => ['nullable', 'uuid'],
        ], $this->mailboxAttachments->validationRules()));

        $template = $this->resolveTemplate($request, $data['template_uuid'] ?? null);
        $draft = ($data['action'] ?? 'send') === 'draft';
        $conversation = Conversation::query()->create([
            'channel' => 'email',
            'contact' => strtolower($data['to']),
            'subject' => $data['subject'],
            'status' => $draft ? 'draft' : 'open',
            'priority' => 'normal',
            'assigned_to' => auth()->id(),
            'metadata' => [
                'source' => 'mailbox_compose',
                'mailbox_folder' => $draft ? 'drafts' : 'sent',
                'draft_body' => $draft ? $data['body'] : null,
                'draft_template_uuid' => $draft ? $template?->uuid : null,
            ],
        ]);
        $attachments = $this->mailboxAttachments->store($request->file('attachments'), $conversation->uuid);

        if ($draft) {
            if ($attachments !== []) {
                $metadata = (array) $conversation->metadata;
                $metadata['draft_attachments'] = $attachments;
                $conversation->update(['metadata' => $metadata]);
            }

            return redirect()->to('/admin/resource/email?folder=drafts&conversation='.$conversation->uuid)
                ->with('success', 'Draft saved.');
        }

        $this->communication->sendReply(
            $conversation,
            $data['body'],
            'mailbox-compose-'.$conversation->uuid,
            $this->deliveryContext($template, $attachments),
        );

        return redirect()->to('/admin/resource/email?folder=sent&conversation='.$conversation->uuid)
            ->with('success', 'Email queued for delivery.');
    }

    public function updateDraft(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->assertEmail($conversation);
        abort_unless($conversation->status === 'draft', 409, 'Only drafts can be edited.');
        $data = $request->validate(array_merge([
            'to' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'template_uuid' => ['nullable', 'uuid'],
        ], $this->mailboxAttachments->validationRules()));
        $template = $this->resolveTemplate($request, $data['template_uuid'] ?? null);

        $metadata = (array) $conversation->metadata;
        $metadata['draft_body'] = $data['body'];
        $metadata['mailbox_folder'] = 'drafts';
        $metadata['draft_template_uuid'] = $template?->uuid;
        $existingAttachments = (array) ($metadata['draft_attachments'] ?? []);
        $attachments = $this->mailboxAttachments->store($request->file('attachments'), $conversation->uuid, count($existingAttachments));
        if ($attachments !== []) {
            $metadata['draft_attachments'] = array_merge($existingAttachments, $attachments);
        }
        $conversation->update([
            'contact' => strtolower($data['to']),
            'subject' => $data['subject'],
            'metadata' => $metadata,
        ]);

        return back()->with('success', 'Draft updated.');
    }

    public function sendDraft(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->assertEmail($conversation);
        abort_unless($conversation->status === 'draft', 409, 'Only drafts can be sent.');
        $data = $request->validate(array_merge([
            'to' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'template_uuid' => ['nullable', 'uuid'],
        ], $this->mailboxAttachments->validationRules()));
        $templateUuid = $data['template_uuid'] ?? data_get($conversation->metadata, 'draft_template_uuid');
        $template = $this->resolveTemplate($request, is_string($templateUuid) ? $templateUuid : null);

        $metadata = (array) $conversation->metadata;
        $existingAttachments = (array) ($metadata['draft_attachments'] ?? []);
        $attachments = array_merge(
            $existingAttachments,
            $this->mailboxAttachments->store($request->file('attachments'), $conversation->uuid, count($existingAttachments)),
        );
        unset($metadata['draft_body'], $metadata['draft_template_uuid']);
        unset($metadata['draft_attachments']);
        $metadata['mailbox_folder'] = 'sent';
        $conversation->update([
            'contact' => strtolower($data['to']),
            'subject' => $data['subject'],
            'status' => 'open',
            'metadata' => $metadata,
        ]);
        $this->communication->sendReply(
            $conversation,
            $data['body'],
            'mailbox-draft-'.$conversation->uuid,
            $this->deliveryContext($template, $attachments),
        );

        return redirect()->to('/admin/resource/email?folder=sent&conversation='.$conversation->uuid)
            ->with('success', 'Draft queued for delivery.');
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->assertEmail($conversation);
        $data = $request->validate(array_merge([
            'body' => ['required', 'string', 'max:100000'],
            'template_uuid' => ['nullable', 'uuid'],
        ], $this->mailboxAttachments->validationRules()));
        $template = $this->resolveTemplate($request, $data['template_uuid'] ?? null);
        $attachments = $this->mailboxAttachments->store($request->file('attachments'), $conversation->uuid);
        $this->communication->sendReply(
            $conversation,
            $data['body'],
            'mailbox-reply-'.Str::uuid(),
            $this->deliveryContext($template, $attachments),
        );

        return back()->with('success', 'Reply queued for delivery.');
    }

    public function trash(Conversation $conversation): RedirectResponse
    {
        $this->assertEmail($conversation);
        $conversation->delete();

        return redirect()->to('/admin/resource/email?folder=trash')->with('success', 'Email moved to Trash.');
    }

    public function restore(string $uuid): RedirectResponse
    {
        $conversation = Conversation::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $this->assertEmail($conversation);
        $conversation->restore();

        return redirect()->to('/admin/resource/email?folder=inbox&conversation='.$conversation->uuid)
            ->with('success', 'Email restored.');
    }

    public function destroy(string $uuid): RedirectResponse
    {
        $conversation = Conversation::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $this->assertEmail($conversation);
        abort_unless($conversation->trashed(), 409, 'Move the email to Trash before deleting permanently.');
        $this->mailboxAttachments->deleteConversationFiles($conversation->messages()->get(), (array) $conversation->metadata);
        $conversation->forceDelete();

        return redirect()->to('/admin/resource/email?folder=trash')->with('success', 'Email permanently deleted.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $recipient = strtolower($data['email']);

        try {
            Mail::raw('Emerald Rozalia email system test. SMTP is accepting outgoing mail from the Communication Center.', function ($message) use ($recipient): void {
                $message->to($recipient)->subject('Emerald Rozalia email system test');
            });
            EmailLog::query()->create([
                'user_id' => auth()->id(),
                'kind' => 'smtp_test',
                'recipient' => $recipient,
                'subject' => 'Emerald Rozalia email system test',
                'status' => 'sent',
                'sent_at' => now(),
                'metadata' => ['source' => 'email_mailbox_setup'],
            ]);
        } catch (Throwable $exception) {
            EmailLog::query()->create([
                'user_id' => auth()->id(),
                'kind' => 'smtp_test',
                'recipient' => $recipient,
                'subject' => 'Emerald Rozalia email system test',
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => Str::limit($exception->getMessage(), 500),
                'metadata' => ['source' => 'email_mailbox_setup'],
            ]);

            return back()->withErrors(['email' => 'SMTP test failed. Check the mail settings and the Email Logs panel.']);
        }

        return back()->with('success', 'Test email sent successfully.');
    }

    public function downloadThread(string $conversationUuid)
    {
        $conversation = $this->emailConversation($conversationUuid);
        $messages = $conversation->messages()->oldest('id')->get();
        $lines = [
            'Subject: '.($conversation->subject ?: '(no subject)'),
            'Contact: '.$conversation->contact,
            'Conversation ID: '.$conversation->uuid,
            '',
        ];

        if ($conversation->status === 'draft') {
            $lines[] = 'DRAFT MESSAGE';
            $lines[] = '';
            $lines[] = (string) data_get($conversation->metadata, 'draft_body', '');
            foreach ((array) data_get($conversation->metadata, 'draft_attachments', []) as $attachment) {
                if (is_array($attachment) && filled($attachment['name'] ?? null)) {
                    $lines[] = '[Attachment: '.(string) $attachment['name'].']';
                }
            }
            $lines[] = '';
            $lines[] = str_repeat('-', 72);
            $lines[] = '';
        }

        foreach ($messages as $message) {
            $lines[] = strtoupper((string) $message->direction).' — '.optional($message->sent_at ?: $message->created_at)->format('d M Y H:i');
            $lines[] = 'Delivery status: '.$message->delivery_status;
            $lines[] = '';
            $lines[] = (string) $message->body;
            foreach ((array) data_get($message->payload, 'attachments', []) as $attachment) {
                if (is_array($attachment) && filled($attachment['name'] ?? null)) {
                    $lines[] = '[Attachment: '.(string) $attachment['name'].']';
                }
            }
            $templateAttachment = (array) data_get($message->payload, 'email_attachment', []);
            if (in_array((string) ($templateAttachment['mode'] ?? ''), ['file', 'file_and_link'], true)
                && filled($templateAttachment['file_name'] ?? null)) {
                $lines[] = '[Attachment: '.(string) $templateAttachment['file_name'].']';
            }
            $lines[] = '';
            $lines[] = str_repeat('-', 72);
            $lines[] = '';
        }

        $filename = 'email-thread-'.substr($conversation->uuid, 0, 8).'.txt';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadMessageAttachment(string $conversationUuid, string $messageUuid, string $attachmentId)
    {
        $conversation = $this->emailConversation($conversationUuid);
        $message = $conversation->messages()->where('uuid', $messageUuid)->firstOrFail();
        $payload = (array) $message->payload;
        $attachment = $this->mailboxAttachments->find((array) ($payload['attachments'] ?? []), $attachmentId, $conversation->uuid);

        if (! $attachment && $attachmentId === 'template') {
            $templateAttachment = (array) ($payload['email_attachment'] ?? []);
            if (in_array((string) ($templateAttachment['mode'] ?? ''), ['file', 'file_and_link'], true)) {
                $path = $this->templateAttachments->assertStoredFileAvailable($templateAttachment);
                $attachment = [
                    'path' => $path,
                    'name' => (string) ($templateAttachment['file_name'] ?? 'email-attachment'),
                    'mime_type' => (string) ($templateAttachment['mime_type'] ?? 'application/octet-stream'),
                ];
            }
        }

        abort_unless($attachment, 404);

        return $this->downloadStoredAttachment($attachment);
    }

    public function downloadDraftAttachment(string $conversationUuid, string $attachmentId)
    {
        $conversation = $this->emailConversation($conversationUuid);
        $metadata = (array) $conversation->metadata;
        $attachment = $this->mailboxAttachments->find((array) ($metadata['draft_attachments'] ?? []), $attachmentId, $conversation->uuid);

        abort_unless($attachment && $conversation->status === 'draft', 404);

        return $this->downloadStoredAttachment($attachment);
    }

    private function resolveTemplate(Request $request, ?string $uuid): ?CommunicationTemplate
    {
        $uuid = trim((string) $uuid);
        if ($uuid === '') {
            return null;
        }

        $template = CommunicationTemplate::query()
            ->where('channel', 'email')
            ->where('status', 'active')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->templateRoles->authorizeUse($request->user(), $template);

        return $template;
    }

    private function deliveryContext(?CommunicationTemplate $template, array $attachments = []): array
    {
        $context = ['attachments' => $attachments];
        if ($template) {
            $context['template_uuid'] = $template->uuid;
            $context['template_name'] = $template->name;
            $attachment = $this->templateAttachments->forTemplate($template);
            if ($attachment) {
                $context['email_attachment'] = $attachment;
            }
        }

        return $context;
    }

    private function emailConversation(string $uuid): Conversation
    {
        $conversation = Conversation::withTrashed()
            ->forCurrentCompany()
            ->where('channel', 'email')
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->assertEmail($conversation);

        return $conversation;
    }

    private function downloadStoredAttachment(array $attachment)
    {
        $path = (string) ($attachment['path'] ?? '');
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        $name = basename(str_replace('\\', '/', (string) ($attachment['name'] ?? 'attachment')));
        $name = trim(str_replace(["\r", "\n", '"'], '', $name)) ?: 'attachment';

        return Storage::disk('local')->download($path, $name, [
            'Content-Type' => (string) ($attachment['mime_type'] ?? 'application/octet-stream'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function folderQuery(string $folder): Builder
    {
        $query = Conversation::query()->forCurrentCompany()->where('channel', 'email');

        if ($folder === 'trash') {
            return Conversation::withTrashed()->forCurrentCompany()->where('channel', 'email')->onlyTrashed()->latest('updated_at');
        }
        if ($folder === 'drafts') {
            return $query->where('status', 'draft')->latest('updated_at');
        }
        if ($folder === 'sent') {
            return $query->where('status', '!=', 'draft')
                ->whereHas('messages', fn (Builder $messages) => $messages->where('direction', 'outbound'))
                ->latest('updated_at');
        }

        return $query->where('status', '!=', 'draft')
            ->where(function (Builder $mail): void {
                $mail->whereHas('messages', fn (Builder $messages) => $messages->where('direction', 'inbound'))
                    ->orWhereDoesntHave('messages');
            })
            ->latest('updated_at');
    }

    private function folderCounts(): array
    {
        $base = fn () => Conversation::query()->forCurrentCompany()->where('channel', 'email');

        return [
            'inbox' => $base()->where('status', '!=', 'draft')->where(function (Builder $mail): void {
                $mail->whereHas('messages', fn (Builder $messages) => $messages->where('direction', 'inbound'))
                    ->orWhereDoesntHave('messages');
            })->count(),
            'sent' => $base()->where('status', '!=', 'draft')->whereHas('messages', fn (Builder $messages) => $messages->where('direction', 'outbound'))->count(),
            'drafts' => $base()->where('status', 'draft')->count(),
            'trash' => Conversation::withTrashed()->forCurrentCompany()->where('channel', 'email')->onlyTrashed()->count(),
            'failed' => ConversationMessage::query()->where('direction', 'outbound')->where('delivery_status', 'failed')
                ->whereHas('conversation', fn (Builder $query) => $query->where('channel', 'email'))->count(),
        ];
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }
        $like = '%'.$search.'%';
        $query->where(function (Builder $mail) use ($like): void {
            $mail->where('contact', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhereHas('messages', fn (Builder $messages) => $messages->where('body', 'like', $like));
        });
    }

    private function mailStatus(): array
    {
        $mailer = (string) config('mail.default', 'log');
        $smtp = (array) config('mail.mailers.smtp', []);
        $from = (array) config('mail.from', []);
        $webhookSecret = (string) config('communication.webhook_secrets.email', '');

        return [
            'mailer' => $mailer,
            'outgoing_configured' => ! in_array($mailer, ['log', 'array'], true),
            'host' => $mailer === 'smtp' ? (string) ($smtp['host'] ?? '—') : 'Managed by '.$mailer,
            'port' => $mailer === 'smtp' ? (string) ($smtp['port'] ?? '—') : '—',
            'from_address' => (string) ($from['address'] ?? '—'),
            'from_name' => (string) ($from['name'] ?? 'Emerald Rozalia'),
            'queue' => (string) config('queue.default', 'sync'),
            'incoming_configured' => trim($webhookSecret) !== '',
            'incoming_webhook' => url('/api/v1/communication/email/inbound'),
            'delivery_webhook' => url('/api/v1/communication/webhooks/email'),
        ];
    }

    private function assertEmail(Conversation $conversation): void
    {
        abort_unless($conversation->channel === 'email', 404);
    }
}
