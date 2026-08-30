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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'ultramsg' => [
        'instance_id' => env('ULTRAMSG_INSTANCE_ID', ''),
        'token' => env('ULTRAMSG_TOKEN', ''),
    ],

    'webpush' => [
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject'     => env('VAPID_SUBJECT', env('APP_URL', 'https://samrtmall.cloud')),
    ],

    'google_drive' => [
        // OAuth 2.0 (حساب شخصي smartmallps2026@gmail.com - My Drive)
        'client_id'     => env('GOOGLE_DRIVE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_OAUTH_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_DRIVE_OAUTH_REDIRECT_URI', 'https://samrtmall.cloud/google-drive/callback'),
        'folder_id'     => env('GOOGLE_DRIVE_FOLDER_ID', '1f-Tep5ghOi0OGUpuyACSC6iFH5XllC-L'),
        'enabled'       => env('GOOGLE_DRIVE_BACKUP_ENABLED', true),
        'backup_time'   => env('GOOGLE_DRIVE_BACKUP_TIME', '03:00'),
        // Legacy Service Account (للتوافق، لم يعد يُستخدم في Backup الجديد)
        'credentials'   => env('GOOGLE_DRIVE_CREDENTIALS', 'storage/app/google/smartmallbackup-331ff1b8f70c.json'),
    ],

];
