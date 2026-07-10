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

    'payos' => [
        'client_id' => env('PAYOS_CLIENT_ID'),
        'api_key' => env('PAYOS_API_KEY'),
        'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
        'base_url' => env('PAYOS_BASE_URL', 'https://api-merchant.payos.vn'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
        'return_url' => env('PAYOS_RETURN_URL'),
        'cancel_url' => env('PAYOS_CANCEL_URL'),
        'ca_bundle' => env('PAYOS_CA_BUNDLE'),
    ],

    'ghn' => [
        'env' => env('GHN_ENV', 'sandbox'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'base_url' => env('GHN_BASE_URL', 'https://dev-online-gateway.ghn.vn'),
        'verify_ssl' => env('GHN_VERIFY_SSL', true),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
        'ca_bundle' => env('CLOUDINARY_CA_BUNDLE') ?: storage_path('certs/cacert.pem'),
    ],

];
