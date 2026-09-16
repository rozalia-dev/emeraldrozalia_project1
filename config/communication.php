<?php

return [
    'channels' => [
        'email' => env('COMMUNICATION_EMAIL_PROVIDER'),
        'whatsapp' => env('COMMUNICATION_WHATSAPP_PROVIDER'),
        'chat' => env('COMMUNICATION_CHAT_PROVIDER', \App\Services\Communication\BrowserChatCommunicationProvider::class),
    ],

    'default_providers' => [
        'email' => \App\Services\Communication\EmailHttpCommunicationProvider::class,
        'whatsapp' => \App\Services\Communication\WhatsAppHttpCommunicationProvider::class,
        'chat' => \App\Services\Communication\ChatHttpCommunicationProvider::class,
    ],

    'endpoints' => [
        'email' => env('COMMUNICATION_EMAIL_ENDPOINT'),
        'whatsapp' => env('COMMUNICATION_WHATSAPP_ENDPOINT'),
        'chat' => env('COMMUNICATION_CHAT_ENDPOINT'),
    ],

    'tokens' => [
        'email' => env('COMMUNICATION_EMAIL_TOKEN'),
        'whatsapp' => env('COMMUNICATION_WHATSAPP_TOKEN'),
        'chat' => env('COMMUNICATION_CHAT_TOKEN'),
    ],

    'timeout' => (int) env('COMMUNICATION_HTTP_TIMEOUT', 15),

    'webhook_secrets' => [
        'email' => env('COMMUNICATION_EMAIL_WEBHOOK_SECRET'),
        'whatsapp' => env('COMMUNICATION_WHATSAPP_WEBHOOK_SECRET'),
        'chat' => env('COMMUNICATION_CHAT_WEBHOOK_SECRET'),
    ],

    // Private localhost-only Node.js WhatsApp Web engine. The same bearer token
    // used by the outbound HTTP provider protects status/QR endpoints as well.
    'whatsapp_engine_url' => env('WHATSAPP_ENGINE_URL', 'http://127.0.0.1:3001'),
    'whatsapp_company_id' => (int) env('COMMUNICATION_WHATSAPP_COMPANY_ID', 0),
];
