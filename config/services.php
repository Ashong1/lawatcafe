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

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
    ],

    'opnsense' => [
        'ip' => env('OPNSENSE_IP', '192.168.2.251'),
        'url' => env('OPNSENSE_API_URL', 'https://192.168.2.251'),
        'key' => env('OPNSENSE_API_KEY'),
        'secret' => env('OPNSENSE_API_SECRET'),
        'zone' => env('OPNSENSE_ZONE', 0),
        'guest_user' => env('OPNSENSE_GUEST_USER', 'laravel_guest'),
        // No hardcoded fallback password: authorizeDevice() refuses to run and
        // logs an error if this isn't explicitly set, rather than silently
        // authorizing guests with a known-weak default credential.
        'guest_pass' => env('OPNSENSE_GUEST_PASS'),
        'block_alias' => env('OPNSENSE_BLOCK_ALIAS', 'guest_blocklist'),
        'tier_alias_free' => env('OPNSENSE_TIER_ALIAS_FREE', 'lawatcafe_free_tier'),
        'tier_alias_premium' => env('OPNSENSE_TIER_ALIAS_PREMIUM', 'lawatcafe_premium_tier'),
        // Same-LAN OPNsense boxes commonly run a self-signed cert; this is an
        // explicit, documented opt-in rather than a silent blanket bypass.
        // Set true (and point PHP at a trusted CA bundle) once OPNsense has a
        // cert your environment actually trusts.
        'verify_tls' => env('OPNSENSE_VERIFY_TLS', false),
    ],

];
