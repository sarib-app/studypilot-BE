<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // See AuthController::googleSignIn/appleSignIn.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    // Native Sign In with Apple verifies the identity token's `aud` claim against our own
    // bundle ID — no client secret/team/key needed unless we later add server-to-server
    // token refresh or revocation, which the MVP doesn't use.
    'apple' => [
        'bundle_id' => env('APPLE_BUNDLE_ID'),
    ],

];
