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

    'sms' => [
        'url' => env('SMS_API_URL', 'https://app.paid2marketing.com/api/v3/sms/send'),
        'token' => env('SMS_API_TOKEN'),
        'sender_id' => env('SMS_SENDER_ID', 'WEBSCEPT'),
    ],

    'cloudns' => [
    'auth_id' => env('CLOUDNS_AUTH_ID'),
    'auth_password' => env('CLOUDNS_AUTH_PASSWORD'),
    'api_url' => env('CLOUDNS_API_URL', 'https://api.cloudns.net'),
],

];
