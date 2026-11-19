<?php

return [
    'handbook_disk' => env(
        'METRIC_HARMONY_HANDBOOK_DISK',
        env('HANDBOOK_FILESYSTEM_DISK', env('FILESYSTEM_DISK', 'local'))
    ),
    'handbook_url_expires_minutes' => (int) env(
        'METRIC_HARMONY_HANDBOOK_URL_EXPIRES_MINUTES',
        (int) env('HANDBOOK_URL_EXPIRES_MINUTES', 15)
    ),
    'features' => [
        'vector' => env('FEATURE_VECTOR', false),
        'sso' => env('FEATURE_SSO', false),
        'sso_invites' => env('FEATURE_SSO_INVITES', false),
        'magic_links' => env('FEATURE_MAGICLINKS', false),
        'workshop_discrepancies' => env('FEATURE_WORKSHOP_DISCREPANCIES', false),
        'public_handbook_links' => env('FEATURE_PUBLIC_HANDBOOK_LINKS', false),
        'change_proposals' => env('FEATURE_CHANGE_PROPOSALS', false),
        'org_metric_overrides' => env('FEATURE_ORG_METRIC_OVERRIDES', false),
        'internal_first_manager_signoff_override' => env('FEATURE_INTERNAL_FIRST_MANAGER_SIGNOFF_OVERRIDE', false),
        // TODO: Remove once Notion/Confluence adapters exist so the wizard can surface drivers again.
        'repository_drivers' => env('FEATURE_REPOSITORY_DRIVERS', false),
    ],
    // TODO: Swap back to a normal array once repository adapters exist; until then this stays feature-gated.
    'repository_drivers' => [
        'notion' => 'Notion',
        'confluence' => 'Atlassian Confluence',
        'google-docs' => 'Google Docs',
    ],
    'engagements' => [
        'metrics_min' => (int) env(
            'METRIC_HARMONY_ENGAGEMENT_METRICS_MIN',
            env('APP_ENV') === 'local' ? 1 : 3
        ),
        'metrics_max' => (int) env('METRIC_HARMONY_ENGAGEMENT_METRICS_MAX', 10),
    ],
    'workshops' => [
        'force_start_enabled' => (bool) env('WORKSHOP_FORCE_START_ENABLED', false),
    ],
    'observability' => [
        'slow_query_threshold_ms' => (int) env('METRIC_HARMONY_SLOW_QUERY_THRESHOLD_MS', 250),
        'log_bindings' => (bool) env('METRIC_HARMONY_LOG_QUERY_BINDINGS', false),
    ],
    'retention' => [
        'notification_attempts_days' => (int) env('METRIC_HARMONY_NOTIFICATION_RETENTION_DAYS', 30),
        'handbook_exports_days' => (int) env('METRIC_HARMONY_HANDBOOK_RETENTION_DAYS', 60),
    ],
];
