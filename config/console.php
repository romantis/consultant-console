<?php

return [
    'allow_public_registration' => (bool) env('CONSOLE_ALLOW_PUBLIC_REGISTRATION', false),

    // Internal org + owner bootstrap (prod-safe)
    'internal_org_slug' => env('CONSOLE_INTERNAL_ORG_SLUG', 'metric-harmony'),
    'internal_org_name' => env('CONSOLE_INTERNAL_ORG_NAME', 'Metric Harmony'),
    'owner_email' => env('CONSOLE_OWNER_EMAIL'),         // required
    'owner_name' => env('CONSOLE_OWNER_NAME', 'Owner'), // optional
    'owner_password' => env('CONSOLE_OWNER_PASSWORD'),      // optional; prefer password-reset flow

    'impersonation' => [
        'cookie_ttl_minutes' => (int) env('CONSOLE_IMPERSONATION_COOKIE_TTL', 60 * 24 * 30),
        'justification_max_length' => (int) env('CONSOLE_IMPERSONATION_JUSTIFICATION_MAX', 500),
    ],
];
