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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'asaas' => [
        'base_url' => env('ASAAS_API_URL', 'https://sandbox.asaas.com/api/v3'),
        'api_key' => env('ASAAS_API_KEY'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'webhook_allowed_ips' => env('ASAAS_WEBHOOK_ALLOWED_IPS', ''),
        'webhook_rate_limit' => env('ASAAS_WEBHOOK_RATE_LIMIT', 60),
        'webhook_signature_secret' => env('ASAAS_WEBHOOK_SIGNATURE_SECRET', ''),
    ],

    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL', ''),
        'api_token' => env('WHATSAPP_API_TOKEN', ''),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),
    ],

];
