<?php

return [

    // The demo account judges sign in with (DatabaseSeeder).
    'demo' => [
        'password' => env('DEMO_PASSWORD') ?: 'password',
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // The assistant's model: the Claude API, or an OpenAI-compatible gateway (9Router, PRD §3.1).
    'llm' => [
        'driver' => env('LLM_DRIVER', 'gateway'),
        'claude' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-haiku-5-5'),
        ],
        'url' => env('LLM_BASE_URL', 'http://localhost:20128/v1'),
        'key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL_TEXT', 'ag/gemini-3.8-flash-low'),
        // Measured 28 Okt: a recipe sentence takes 15-35 s through 9Router, a sale 5-10 s.
        'timeout' => (int) env('LLM_TIMEOUT', 40),
        // Photos of receipts take about twice as long to read as a message.
        'vision_model' => env('LLM_MODEL_VISION', env('LLM_MODEL_TEXT', 'ag/gemini-3.8-flash-low')),
        'vision_timeout' => (int) env('LLM_TIMEOUT_VISION', 45),
    ],

    // The chat app the bot runs on: telegram, or whatsapp through GOWA.
    'bot' => [
        'channel' => env('BOT_CHANNEL', 'telegram'),
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        // Without the @, as BotFather shows it.
        'username' => env('TELEGRAM_BOT_USERNAME'),
    ],

    // GOWA (go-whatsapp-web-multidevice): the bot's WhatsApp number. Never reachable from the internet.
    'gowa' => [
        'url' => env('GOWA_URL', 'http://127.0.0.1:3000'),
        // user:pass, as GOWA's APP_BASIC_AUTH.
        'basic_auth' => env('GOWA_BASIC_AUTH'),
        // Newer GOWA versions require X-Device-Id on every send.
        'device_id' => env('GOWA_DEVICE_ID', 'lais'),
        // GOWA's WHATSAPP_WEBHOOK_SECRET; its default "secret" must be replaced.
        'webhook_secret' => env('GOWA_WEBHOOK_SECRET'),
        // The bot's number in 62… form, shown to owners so they can message it.
        'bot_number' => env('GOWA_BOT_NUMBER'),
    ],

];
