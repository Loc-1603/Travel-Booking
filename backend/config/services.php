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

    /*
    |--------------------------------------------------------------------------
    | VNPay (payments)
    |--------------------------------------------------------------------------
    |
    | Single IPN URL serves hotel + tour (UnifiedVnpayIpnController routes by
    | vnp_TxnRef prefix). Register VNPAY_IPN_URL once on the VNPay portal.
    |
    */
    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL', 'http://localhost:5173/vnpay-return'),
        'ipn_url' => env('VNPAY_IPN_URL', ''),
        'version' => env('VNPAY_VERSION', '2.1.0'),
        'currency' => env('VNPAY_CURR_CODE', 'VND'),
        'locale' => env('VNPAY_LOCALE', 'vn'),
        'expire_minutes' => env('VNPAY_EXPIRE_MINUTES', 30),
    ],

    'unsplash' => [
        'key' => env('UNSPLASH_ACCESS_KEY'),
    ],

];
