<?php

return [
    'channels' => [
        'email' => env('COMMUNICATION_EMAIL_PROVIDER'),
        'whatsapp' => env('COMMUNICATION_WHATSAPP_PROVIDER'),
        'chat' => env('COMMUNICATION_CHAT_PROVIDER'),
    ],

    'webhook_secrets' => [
        'email' => env('COMMUNICATION_EMAIL_WEBHOOK_SECRET'),
        'whatsapp' => env('COMMUNICATION_WHATSAPP_WEBHOOK_SECRET'),
        'chat' => env('COMMUNICATION_CHAT_WEBHOOK_SECRET'),
    ],
];
