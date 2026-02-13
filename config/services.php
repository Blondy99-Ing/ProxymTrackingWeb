<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'dashboard_webhook_secret' => env('DASHBOARD_WEBHOOK_SECRET'),

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL'),
        'sender_name' => env('BREVO_SENDER_NAME', 'PROXYM TRACKING'),
        'template_reset_id' => (int) env('BREVO_TEMPLATE_RESET_ID', 2),
    ],

    'techsoft_sms' => [
        'token' => env('TECHSOFT_SMS_API_TOKEN'),
        'sender_id' => env('TECHSOFT_SMS_SENDER_ID', 'TECHSOF-SMS'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],

    // ✅ Google Roads (Snap To Roads) — optionnel mais “route stricte”
    'google_roads' => [
        'key' => env('GOOGLE_ROADS_API_KEY', env('GOOGLE_MAPS_KEY')),
        'interpolate' => (bool) env('GOOGLE_ROADS_INTERPOLATE', true),
        'chunk' => (int) env('GOOGLE_ROADS_CHUNK', 100),
        'timeout' => (int) env('GOOGLE_ROADS_TIMEOUT', 15),
        'retries' => (int) env('GOOGLE_ROADS_RETRIES', 2),
    ],

];