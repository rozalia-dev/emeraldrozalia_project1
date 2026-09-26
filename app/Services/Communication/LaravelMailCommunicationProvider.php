<?php

namespace App\Services\Communication;

use App\Contracts\CommunicationProvider;
use App\Models\ConversationMessage;
use App\Services\CommunicationTemplateAttachmentService;
use App\Support\CommunicationSendResult;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class LaravelMailCommunicationProvider implements CommunicationProvider
{
    public function send(ConversationMessage $message): CommunicationSendResult
    {
        $message->loadMissing('conversation');
        $conversation = $message->conversation;
        $recipient = strtolower(trim((string) $conversation?->contact));

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'recipient' => 'The email conversation does not have a valid recipient address.',
            ]);
        }

        $subject = trim((string) ($conversation?->subject ?: 'Emerald Rozalia'));
        $attachment = data_get($message->payload, 'email_attachment');
        $attachment = is_array($attachment) ? $attachment : [];
        $mode = (string) ($attachment['mode'] ?? 'none');
        $body = (string) $message->body;

        if (in_array($mode, ['link', 'file_and_link'], true) && filled($attachment['url'] ?? null)) {
            $label = trim((string) ($attachment['label'] ?? 'Open attachment')) ?: 'Open attachment';
            $body = rtrim($body)."\n\n{$label}:\n".(string) $attachment['url'];
        }

        $fileAttachments = [];
        foreach ((array) data_get($message->payload, 'attachments', []) as $uploadedAttachment) {
            if (! is_array($uploadedAttachment)) {
                continue;
            }

            $storedPath = (string) ($uploadedAttachment['path'] ?? '');
            if (! str_starts_with($storedPath, 'communication/mailbox/'.(string) $conversation?->uuid.'/')
                || ! Storage::disk('local')->exists($storedPath)) {
                throw ValidationException::withMessages([
                    'attachment_file' => 'An email attachment is missing or has an invalid storage path.',
                ]);
            }

            $fileAttachments[] = [
                'path' => Storage::disk('local')->path($storedPath),
                'name' => (string) ($uploadedAttachment['name'] ?? ''),
                'mime_type' => (string) ($uploadedAttachment['mime_type'] ?? ''),
            ];
        }

        if (in_array($mode, ['file', 'file_and_link'], true)) {
            $storedPath = app(CommunicationTemplateAttachmentService::class)->assertStoredFileAvailable($attachment);
            $fileAttachments[] = [
                'path' => Storage::disk('local')->path($storedPath),
                'name' => (string) ($attachment['file_name'] ?? ''),
                'mime_type' => (string) ($attachment['mime_type'] ?? ''),
            ];
        }

        Mail::raw($body, function ($mail) use ($recipient, $subject, $fileAttachments): void {
            $mail->to($recipient)->subject($subject);

            foreach ($fileAttachments as $fileAttachment) {
                $options = [];
                if (filled($fileAttachment['name'] ?? null)) {
                    $options['as'] = (string) $fileAttachment['name'];
                }
                if (filled($fileAttachment['mime_type'] ?? null)) {
                    $options['mime'] = (string) $fileAttachment['mime_type'];
                }
                $mail->attach((string) $fileAttachment['path'], $options);
            }
        });

        // SMTP acceptance is not proof of inbox delivery. A configured provider
        // webhook can later promote this queued status to delivered or failed.
        return new CommunicationSendResult('accepted');
    }
}
