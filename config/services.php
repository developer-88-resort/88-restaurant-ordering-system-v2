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

    // Maya Checkout (online payment). Off unless MAYA_CHECKOUT_ENABLED is
    // set, so a deploy without keys never shows the option to cashiers.
    // Sandbox: https://pg-sandbox.paymaya.com, production: https://pg.maya.ph.
    'maya' => [
        'enabled' => (bool) env('MAYA_CHECKOUT_ENABLED', false),
        'base_url' => env('MAYA_BASE_URL', 'https://pg-sandbox.paymaya.com'),
        'public_key' => env('MAYA_PUBLIC_KEY'),
        'secret_key' => env('MAYA_SECRET_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
