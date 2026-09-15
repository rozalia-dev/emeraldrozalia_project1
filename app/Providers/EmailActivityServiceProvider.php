<?php

namespace App\Providers;

use App\Listeners\LogEmailNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EmailActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotificationSent::class, [LogEmailNotification::class, 'sent']);
        Event::listen(NotificationFailed::class, [LogEmailNotification::class, 'failed']);
    }
}
