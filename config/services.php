<?php

return [

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

    // The assistant's model, behind an OpenAI-compatible gateway (9Router, PRD §3.1).
    'llm' => [
        'url' => env('LLM_BASE_URL', 'http://localhost:20128/v1'),
        'key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL_TEXT', 'ag/gemini-3.8-flash-low'),
        // Measured 28 Okt: a recipe sentence takes 15-35 s through 9Router, a sale 5-10 s.
        'timeout' => (int) env('LLM_TIMEOUT', 40),
        // Photos of receipts take about twice as long to read as a message.
        'vision_model' => env('LLM_MODEL_VISION', env('LLM_MODEL_TEXT', 'ag/gemini-3.8-flash-low')),
        'vision_timeout' => (int) env('LLM_TIMEOUT_VISION', 45),
    ],

];
