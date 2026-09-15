<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LogEmailNotification
{
    public function sent(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $this->write($event->notifiable, $event->notification, 'sent', null);
    }

    public function failed(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $reason = data_get($event->data, 'exception.message')
            ?: data_get($event->data, 'message')
            ?: 'The mail notification channel reported a delivery failure.';

        $this->write($event->notifiable, $event->notification, 'failed', Str::limit((string) $reason, 500));
    }

    private function write(object $notifiable, object $notification, string $status, ?string $failure): void
    {
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail', $notification)
            : ($notifiable->email ?? null);

        if (is_array($route)) {
            $recipient = (string) array_key_first($route);
            if ($recipient === '' || is_int(array_key_first($route))) {
                $recipient = (string) Arr::first($route);
            }
        } else {
            $recipient = (string) $route;
        }

        $recipient = strtolower(trim($recipient));
        if ($recipient === '') {
            return;
        }

        $kind = class_basename($notification);
        $subject = match ($kind) {
            'VerifyEmail' => 'Verify your Emerald Rozalia email address',
            'ResetPassword' => 'Reset your Emerald Rozalia password',
            default => Str::headline($kind),
        };

        EmailLog::query()->create([
            'user_id' => $notifiable->getKey() ?? null,
            'kind' => $kind,
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'failure_message' => $failure,
            'metadata' => [
                'notification_class' => get_class($notification),
                'source' => 'laravel_notification',
            ],
        ]);
    }
}
