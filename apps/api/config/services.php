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

    // Web Push (VAPID) for the app's notifications: `php artisan notifications:vapid-keys` makes a pair.
    // Alerts to the platform team (server errors, health): a Telegram bot and the chat it writes to.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    ],

    // /api/v1/health: below this much free disk it fails.
    'monitoring' => [
        'min_free_percent' => (int) env('MONITOR_MIN_FREE_PERCENT', 10),
    ],

    // /api/v1/diagnostics/{error,slow,hang}: misbehave on purpose to test a monitor. Empty token = off (404).
    'diagnostics' => [
        'token' => env('DIAGNOSTICS_TOKEN', ''),
        'hang_seconds' => (int) env('DIAGNOSTICS_HANG_SECONDS', 120),
    ],

    // Each account's student platform lives at {slug}.{host} (e.g. mr-mohamed.manhagy.online).
    'sites' => [
        'host' => env('SITES_HOST', 'manhagy.online'),
    ],

    // The video provider behind Media\Contracts\VideoHost. `fake` until one is chosen (docs/TECH_PLAN.md §14).
    'video' => [
        'driver' => env('VIDEO_DRIVER', 'fake'),
        'fake_duration' => (int) env('VIDEO_FAKE_DURATION', 600),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:support@manhagy.online'),
    ],

    // Google Play app (Trusted Web Activity): its package and signing key fingerprint(s), comma separated.
    'twa' => [
        'package' => env('TWA_PACKAGE', ''),
        'fingerprints' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('TWA_SHA256', ''))))),
    ],

    // Passkeys (unlocking the app with a fingerprint / face). rp_id = the app's host; origins = the
    // pages allowed to use them (comma separated, default APP_URL).
    // Meta (Facebook) Conversions API: sign-ups / contacts sent by the server too (same pixel / dataset).
    'meta' => [
        'pixel_id' => (string) env('META_PIXEL_ID', ''),
        'capi_token' => env('META_CAPI_TOKEN', ''),
        'test_event_code' => env('META_TEST_EVENT_CODE', ''),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
    ],

    // Shops signing up on their own (the platform admins switch it; this is the default until they do).
    'signup' => [
        'open' => (bool) env('SIGNUP_OPEN', false),
        'whatsapp' => env('SIGNUP_WHATSAPP', '+201555440882'),
    ],

    // Students prove their phone by sending a WhatsApp message to the platform's number (no message is sent to them).
    // Off while VERIFY_WHATSAPP is empty. The inbound webhook is Meta's WhatsApp Cloud API (signed with the app secret);
    // with a token + phone number id the platform also answers «اتأكد رقمك» in the same chat.
    'verify' => [
        'whatsapp' => preg_replace('/\D+/', '', (string) env('VERIFY_WHATSAPP', '')),
        'app_secret' => (string) env('WHATSAPP_APP_SECRET', ''),
        'verify_token' => (string) env('WHATSAPP_VERIFY_TOKEN', ''),
        'token' => (string) env('WHATSAPP_TOKEN', ''),
        'phone_number_id' => (string) env('WHATSAPP_PHONE_ID', ''),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        'minutes' => 30,
    ],

    'webauthn' => [
        'rp_id' => env('WEBAUTHN_RP_ID', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        'origins' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('WEBAUTHN_ORIGINS', env('APP_URL', 'http://localhost')))))),
    ],

];
