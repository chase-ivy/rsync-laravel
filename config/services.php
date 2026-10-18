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

        // RSYNC room photos. PHOTOS_DRIVER=local → public/images (your computer);
    // PHOTOS_DRIVER=supabase → a public Supabase Storage bucket (Render). See App\Support\RoomPhotos.
    'photos' => [
        'driver' => env('PHOTOS_DRIVER', 'local'),
         'supabase_url' => env('SUPABASE_URL'),                 // https://<project-ref>.supabase.co
        'supabase_key' => env('SUPABASE_SECRET_KEY'),        // secret / service_role key — server only, never in JS      // secret / service_role key — server only, never in JS
        'bucket' => env('SUPABASE_BUCKET', 'room-photos'),
    ],

        // RSYNC emails through Brevo's API (MAIL_MAILER=brevo). The key goes in .env / Render only.
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

];
