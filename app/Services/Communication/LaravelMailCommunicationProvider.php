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

        $filePath = null;
        if (in_array($mode, ['file', 'file_and_link'], true)) {
            $storedPath = app(CommunicationTemplateAttachmentService::class)->assertStoredFileAvailable($attachment);
            $filePath = Storage::disk('local')->path($storedPath);
        }

        Mail::raw($body, function ($mail) use ($recipient, $subject, $attachment, $filePath): void {
            $mail->to($recipient)->subject($subject);

            if ($filePath) {
                $options = [];
                if (filled($attachment['file_name'] ?? null)) {
                    $options['as'] = (string) $attachment['file_name'];
                }
                if (filled($attachment['mime_type'] ?? null)) {
                    $options['mime'] = (string) $attachment['mime_type'];
                }
                $mail->attach($filePath, $options);
            }
        });

        // SMTP acceptance is not proof of inbox delivery. A configured provider
        // webhook can later promote this queued status to delivered or failed.
        return new CommunicationSendResult('accepted');
    }
}
