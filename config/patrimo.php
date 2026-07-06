<?php

use App\Support\RoleName;

return [

    /*
    |--------------------------------------------------------------------------
    | Security policy knobs (Security.md §2)
    |--------------------------------------------------------------------------
    | Role-driven security obligations are configuration data, not code
    | branches: adjusting who must use MFA or gets a short idle timeout is a
    | config change, not a refactor.
    */

    'security' => [

        // Roles for which app (TOTP) multi-factor authentication is mandatory.
        'mfa_required_roles' => [
            RoleName::GESTIONNAIRE_PATRIMOINE,
            RoleName::RESPONSABLE_FACULTE,
            RoleName::RECTORAT,
            RoleName::SUPER_ADMIN,
        ],

        // Roles subject to the short idle timeout below.
        'elevated_roles' => [
            RoleName::GESTIONNAIRE_PATRIMOINE,
            RoleName::RESPONSABLE_FACULTE,
            RoleName::RECTORAT,
            RoleName::SUPER_ADMIN,
        ],

        // Idle minutes before an elevated-role session is forcibly ended.
        'elevated_idle_timeout_minutes' => env('PATRIMO_ELEVATED_IDLE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Self-service registration (user decision 2026-07-04)
    |--------------------------------------------------------------------------
    | Open only to institutional email domains; the account gets the
    | tout_utilisateur role and must verify its email before panel access.
    | Admin-provisioned accounts are pre-verified (trusted entry).
    */

    'registration' => [
        'enabled' => env('PATRIMO_REGISTRATION_ENABLED', true),

        // Exact-match domain allowlist (comma-separated in env).
        // TODO(confirm): the definitive list of UBMA domains (e.g. student
        // subdomains like etu.univ-annaba.dz) with the university.
        'allowed_domains' => explode(',', (string) env('PATRIMO_REGISTRATION_DOMAINS', 'univ-annaba.dz')),

        // Hourly per-IP cap, on top of Filament's built-in per-minute and
        // per-email limits (Security.md §5).
        'max_per_hour_per_ip' => (int) env('PATRIMO_REGISTRATION_MAX_PER_HOUR', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Room reservations (Phase 5 — PROGRESS.md open question #5)
    |--------------------------------------------------------------------------
    | TODO(confirm): the request throttle is a conservative default, not
    | confirmed university policy — flagged in PROGRESS.md, not final.
    | The old "max_recurrence_months" arbitrary cap is gone (2026-07-06
    | addendum) — a faculty-entered weekly slot now recurs to its Academic
    | Term's end_date, the authoritative boundary.
    */

    'reservations' => [
        // Per-user hourly cap on ad-hoc Enseignant requests (Security.md §5
        // — "per-user throttle to prevent slot-spamming/hoarding").
        'request_max_per_hour' => (int) env('PATRIMO_RESERVATION_REQUEST_MAX_PER_HOUR', 10),
    ],

];
