<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // OpenRouter LLM gateway (ADR 0017). The base URL is app-wide config; the
    // API key is stored per-owner (encrypted) in `provider_credentials`, not in
    // the environment, so each account uses its own key (S-5.1.1, PH-18).
    //
    // Transport tunables for the thin `OpenRouterClient` (S-5.2.x): request and
    // connect timeouts, the bounded retry/backoff envelope (429/5xx + malformed
    // structured output), and the debug gate that decides whether full message
    // bodies are persisted to the save-realm-sensitive `llm_calls` log (S-5.3.2).
    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 60),
        'connect_timeout' => (int) env('OPENROUTER_CONNECT_TIMEOUT', 10),
        'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 2),
        'retry_base_delay_ms' => (int) env('OPENROUTER_RETRY_BASE_DELAY_MS', 250),
        'log_messages' => (bool) env('OPENROUTER_LOG_MESSAGES', false),
    ],

];
