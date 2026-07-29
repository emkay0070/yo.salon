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

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
    ],

    'mtn' => [
        'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),
        'api_key' => env('MTN_API_KEY'),
        'api_user' => env('MTN_API_USER'),
        'environment' => env('MTN_ENVIRONMENT', 'sandbox'),
        'callback_url' => env('MTN_CALLBACK_URL'),
        'base_url' => env('MTN_BASE_URL'),
    ],

    'airtel' => [
        'client_id' => env('AIRTEL_CLIENT_ID'),
        'client_secret' => env('AIRTEL_CLIENT_SECRET'),
        'country' => env('AIRTEL_COUNTRY', 'UG'),
        'currency' => env('AIRTEL_CURRENCY', 'UGX'),
        'environment' => env('AIRTEL_ENVIRONMENT', 'sandbox'),
        'callback_url' => env('AIRTEL_CALLBACK_URL'),
        'base_url' => env('AIRTEL_BASE_URL'),
    ],

];
