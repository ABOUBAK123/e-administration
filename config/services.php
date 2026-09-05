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

    'ollama' => [
        'url'     => env('OLLAMA_URL', 'http://localhost:11434'),
        'model'   => env('OLLAMA_MODEL', 'llama3'),
        'timeout' => env('OLLAMA_TIMEOUT', 60),
    ],

    'gemini' => [
        // Clé API gratuite : https://aistudio.google.com/app/apikey
        'api_key' => env('GEMINI_API_KEY'),
        'model'   => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'timeout' => env('GEMINI_TIMEOUT', 120),
    ],

    'signature_platform' => [
        // Secret partagé pour authentifier les webhooks entrants de la plateforme de signature.
        // Générer avec : php artisan tinker --execute="echo bin2hex(random_bytes(32));"
        'webhook_secret' => env('SIGNATURE_WEBHOOK_SECRET'),
    ],

    'mobile_money' => [
        // Secret partagé pour authentifier les webhooks entrants des fournisseurs mobile money.
        // Générer avec : php artisan tinker --execute="echo bin2hex(random_bytes(32));"
        'webhook_secret' => env('MOBILE_MONEY_WEBHOOK_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'clamav' => [
        'enabled' => env('CLAMAV_ENABLED', false),
        'socket'  => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
    ],

];
