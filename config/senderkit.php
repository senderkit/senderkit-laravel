<?php

declare(strict_types=1);

return [
    // sk_live_… or sk_test_… API key.
    'api_key' => env('SENDERKIT_API_KEY', ''),

    'base_url' => env('SENDERKIT_BASE_URL', 'https://api.senderkit.com'),

    // Request timeout in milliseconds.
    'timeout_ms' => (int) env('SENDERKIT_TIMEOUT_MS', 30000),

    'max_retries' => (int) env('SENDERKIT_MAX_RETRIES', 2),

    // whsec_… secret used by the webhook signature middleware.
    'webhook_secret' => env('SENDERKIT_WEBHOOK_SECRET'),
];
