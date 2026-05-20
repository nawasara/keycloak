<?php

return [
    // Token cache TTL in seconds (Keycloak default token lifetime = 60s)
    'token_ttl' => 55,

    // Users per page when listing
    'users_per_page' => 20,

    // Events per page
    'events_per_page' => 25,

    // Scheduler — registers keycloak:sync (users + clients) on the Laravel
    // schedule, hourly. Set KEYCLOAK_SCHEDULER_ENABLED false to skip
    // registration, e.g. when the deployment has no Keycloak admin
    // credentials yet (the scheduled task would just fail every run).
    'scheduler' => [
        'enabled' => env('KEYCLOAK_SCHEDULER_ENABLED', true),
    ],
];
