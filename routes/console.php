<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command;

Artisan::command('er:status', function (): void {
    $this->info('Emerald Rozalia platform OK');
});

Artisan::command('er:mail-test {email}', function (string $email): int {
    $mailer = (string) config('mail.default');
    $from = (string) config('mail.from.address');

    $this->line('Mailer: '.$mailer);
    $this->line('From: '.$from);

    if (in_array($mailer, ['log', 'array'], true)) {
        $this->error('External email delivery is disabled. Set MAIL_MAILER=smtp and the SMTP values in .env first.');

        return Command::FAILURE;
    }

    try {
        Mail::raw(
            'Emerald Rozalia mail delivery test. If you received this message, SMTP delivery is working.',
            function ($message) use ($email): void {
                $message->to($email)->subject('Emerald Rozalia mail test');
            }
        );
    } catch (Throwable $exception) {
        report($exception);
        $this->error('Mail delivery failed: '.$exception->getMessage());

        return Command::FAILURE;
    }

    $this->info('Test email accepted by the configured mail transport for '.$email.'.');

    return Command::SUCCESS;
})->purpose('Send a live SMTP test message without exposing mail credentials.');
