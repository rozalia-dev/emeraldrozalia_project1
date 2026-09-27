<?php
return [
    'meshy' => [
        'key' => env('MESHY_API_KEY'),
        'base_url' => env('MESHY_API_URL', 'https://api.meshy.ai/openapi/v1'),
    ],
    'fashion_ai' => [
        'key' => env('FASHION_AI_API_KEY'),
        'url' => env('FASHION_AI_API_URL'),
    ],
];
