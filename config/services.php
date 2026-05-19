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

    'opnsense' => [
        'ip' => env('OPNSENSE_IP', '192.168.2.251'),
        'url' => env('OPNSENSE_API_URL', 'https://192.168.2.251'),
        'key' => env('OPNSENSE_API_KEY'),
        'secret' => env('OPNSENSE_API_SECRET'),
        'zone' => env('OPNSENSE_ZONE', 0),
        'guest_user' => env('OPNSENSE_GUEST_USER', 'laravel_guest'),
        'guest_pass' => env('OPNSENSE_GUEST_PASS', 'Laravel123'),
    ],

];
