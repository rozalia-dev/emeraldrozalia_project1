<?php

namespace App\Services\Communication;

use App\Contracts\CommunicationProvider;
use App\Models\ConversationMessage;
use App\Support\CommunicationSendResult;
use Illuminate\Support\Facades\Mail;
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
        Mail::raw($message->body, function ($mail) use ($recipient, $subject): void {
            $mail->to($recipient)->subject($subject);
        });

        // SMTP acceptance is not proof of inbox delivery. A configured provider
        // webhook can later promote this queued status to delivered or failed.
        return new CommunicationSendResult('accepted');
    }
}
