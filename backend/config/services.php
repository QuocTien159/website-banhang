<?php

return [

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

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
        'ca_bundle' => env('GHN_CA_BUNDLE'),
        'timeout' => env('GHN_TIMEOUT', 12),
        'webhook_secret' => env('GHN_WEBHOOK_SECRET'),
        'from_name' => env('GHN_FROM_NAME'),
        'from_phone' => env('GHN_FROM_PHONE'),
        'from_address' => env('GHN_FROM_ADDRESS'),
        'from_province_id' => env('GHN_FROM_PROVINCE_ID'),
        'from_district_id' => env('GHN_FROM_DISTRICT_ID'),
        'from_ward_code' => env('GHN_FROM_WARD_CODE'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
        'ca_bundle' => env('CLOUDINARY_CA_BUNDLE') ?: storage_path('certs/cacert.pem'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
        // Windows PHP installations may not include a current CA store. Keep TLS verification enabled.
        'ca_bundle' => env('GOOGLE_CA_BUNDLE') ?: storage_path('certs/cacert.pem'),
    ],

];
