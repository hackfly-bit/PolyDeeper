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

    'polymarket' => [
        'gamma_host' => env('POLYMARKET_GAMMA_HOST', 'https://gamma-api.polymarket.com'),
        'clob_host' => env('POLYMARKET_CLOB_HOST', 'https://clob.polymarket.com'),
        'data_host' => env('POLYMARKET_DATA_HOST', 'https://data-api.polymarket.com'),
        'chain_id' => (int) env('POLYMARKET_CHAIN_ID', 137),
        'address' => env('POLYMARKET_ADDRESS'),
        'signature_type' => (int) env('POLYMARKET_SIGNATURE_TYPE', 0),
        'funder' => env('POLYMARKET_FUNDER'),
        'signer_private_key' => env('POLYMARKET_SIGNER_PRIVATE_KEY'),
        'api_key' => env('POLYMARKET_API_KEY'),
        'api_secret' => env('POLYMARKET_API_SECRET'),
        'api_passphrase' => env('POLYMARKET_API_PASSPHRASE'),
        'timeout_seconds' => (int) env('POLYMARKET_TIMEOUT_SECONDS', 15),
        'tls_verify' => filter_var(env('POLYMARKET_TLS_VERIFY', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        'ca_bundle' => env('POLYMARKET_CA_BUNDLE'),
    ],

];
