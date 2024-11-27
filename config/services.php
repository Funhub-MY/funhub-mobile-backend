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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_KEY'),
        'client_secret' => env('FACEBOOK_SECRET'),
        'redirect' => 'https://funhub-backend.dev.com/auth/facebook/callback'
    ],
    // google redirect cannot use .test as domain. need to use top level domain such as .org, .com etc.
    'google' => [
        'client_id' => env('GOOGLE_KEY'),
        'client_secret' => env('GOOGLE_SECRET'),
        'redirect' => 'https://funhub-backend.dev.com/auth/google/callback'
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'movider' => [
        'key' => env('MOVIDER_KEY'),
        'secret' => env('MOVIDER_SECRET'),
        'api_url' => env('MOVIDER_API', 'https://api.movider.co/v1/sms'),
    ],

    'mpay' => [
        'uat_url' => env('MPAY_UAT_URL', 'https://pcimdex.mpay.my/mdex2/'),
        'prod_url' => env('MPAY_PROD_URL', ' https://mpaypayment.mpay.my/mdex/'),
        'mid' => env('MPAY_MID'),
        'hash_key' => env('MPAY_HASH_KEY'),
        'mid_card_only' => env('MPAY_MID_CARD_ONLY'),
        'hash_key_card_only' => env('MPAY_HASH_KEY_CARD_ONLY'),
        'mid_fpx_only' => env('MPAY_MID_FPX_ONLY'),
        'hash_key_fpx_only' => env('MPAY_HASH_KEY_FPX_ONLY'),
    ],

    'openai' => [
        'secret' => env('OPENAI_SECRET'),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'user_token' => env('ONESIGNAL_APP_USER_TOKEN')
    ],

    'hubspot' => [
        'token' => env('HUBSPOT_TOKEN'),
    ],

    'bubble' => [
        'status' => env('BUBBLE_STATUS', false),
        'root_url' => env('BUBBLE_ROOT_URL', 'https://rating.funhub.my/version-test/api/1.1/obj'),
        'api_key' => env('BUBBLE_API_KEY'),
    ],

    'byteplus' => [
        'enabled_vod' => env('BYTEPLUS_ENABLED_VOD', false),
        'key' => env('BYTEPLUS_KEY'),
        'secret' => env('BYTEPLUS_SECRET'),
        'vod_space' => env('BYTEPLUS_VOD_SPACE'),
        'vod_template_id' => env('BYTEPLUS_VOD_TEMPLATE_ID'),
        'vod_region' => env('BYTEPLUS_VOD_REGION', 'ap-singapore-1'),
        'sms_url' => env('BYTEPLUS_SMS_URL', 'https://sms.byteplusapi.com/sms/openapi/send_sms'),
        'sms_account' => env('BYTEPLUS_SMS_ACCOUNT'),
        'sms_password' => env('BYTEPLUS_SMS_PASSWORD'),
    ],
];
