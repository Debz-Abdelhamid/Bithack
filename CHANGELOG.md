# Changelog

All notable changes to the PUI-UBMA R13 Patrimoine module.
Format: one entry per phase (see `Phases.md`), Conventional-Commit-style categories.

## Hardening sprint — gap closure before Phase 2 (2026-07-05)

### Added
- **Audit logging completed per Security.md §8**: auth events (`login`, `logout`,
  `login_failed` with IP+email, `password_reset`) and RBAC changes (role attach/detach,
  permission grant/revoke via spatie events + auditable `App\Models\Role`) all land in the
  activity log under `auth`/`rbac` log names.
- **Account deactivation** (Security.md §13): `users.is_active` toggle in the admin form;
  deactivated accounts fail `canAccessPanel()` on their next request — instant lockout, no
  data deletion.
- **Backups with tested restore** (Security.md §7): `backup` sidecar dumps daily to
  `storage/backups/` (7d/4w/6m retention); restore procedure documented in README and
  verified against a scratch database.
- **PostgreSQL CI job**: the whole Pest suite now also runs against Postgres 16 in CI,
  closing the sqlite blind spot that hid the `jsonb` bug.

### Changed
- **English is now the primary UI language** (project-owner decision, reversing the initial
  French-first plan): `APP_LOCALE=en`, brand subtitle "Asset Management", demo data in
  English. Every string still flows through `lang/` files — `lang/fr` is maintained in
  parallel, so a French pass is a locale switch, not a refactor.
- **App timezone set to `Africa/Algiers`** before any time-arithmetic features land
  (reservations, SLA); business-day calendar remains `TODO(confirm)`.
- 9 new Pest tests (47 total, green on sqlite and pgsql).

## Phase 1 addendum — Self-service registration & booking policy (2026-07-04)

### Added
- Self-service registration (user decision): custom Filament Register page restricted to
  institutional email domains (`PATRIMO_REGISTRATION_DOMAINS`, exact match, `TODO(confirm)`
  final list), auto-assigns exactly `tout_utilisateur`, hourly per-IP cap on top of Filament's
  built-in limits, toggleable without deploy (`PATRIMO_REGISTRATION_ENABLED`).
- Mandatory email verification for self-registered accounts (`->emailVerification()` +
  `MustVerifyEmail`); **admin-provisioned and seeded accounts are created pre-verified** so
  the gate never blocks provisioning.
- Mailpit container for local mail (UI at http://localhost:8025); `MAIL_MAILER=smtp` →
  `mailpit:1025`. Production SMTP remains `TODO(confirm)`.
- 7 new Pest tests (37 total... 30 passing suite): domain denial, role assignment + hash
  integrity, verification gate, least-privilege after verify, per-IP throttle, seeded accounts
  pre-verified.

### Added (follow-up)
- Password reset ("Mot de passe oublié" link on login → emailed reset link, Filament built-in
  throttling, no account-existence disclosure) and email-change verification (a new address
  must be confirmed by mail before it replaces the old one). 3 new Pest tests (33 total).
- Registration/user forms now label the name field "Nom & Prénom" (`fields.full_name`).

### Added (user decision)
- **Admin provisioning now enforces the institutional allowlist too**: the Utilisateurs
  create/edit form applies `InstitutionalEmailDomain` (unchanged emails on existing records
  grandfathered, so legacy/demo accounts stay editable; seeders bypass forms and are
  unaffected). 4 new Pest tests (40 total).

### Fixed
- **`email_verified_at` was silently discarded by mass assignment** (not in `$fillable`):
  the "provisioned accounts are pre-verified" behavior never actually persisted — neither
  from the admin create form nor from `DemoSeeder` (the live demo accounts only worked due
  to a manual SQL backfill). Column added to `$fillable` (never exposed in any form);
  provisioning + seeding now genuinely pre-verify.

### Fixed (user-reported)
- **Domain-allowlist bypass via profile email change**: the profile page let any user swap
  their institutional email for an arbitrary one, sidestepping the registration gate.
  The allowlist rule is now a shared `App\Rules\InstitutionalEmailDomain` applied on both
  registration and the profile's email change (custom `EditProfile` page). Existing
  out-of-domain addresses (admin-provisioned/demo) are grandfathered until actually changed;
  Filament additionally requires the current password for the change, and the new address
  still needs mail verification. 3 more Pest tests (36 total).

### Changed
- **Booking policy (project-owner decision, overrides source doc):** reservation initiation is
  now **Enseignant-only**; `tout_utilisateur` gets read-only timetable/availability viewing +
  QR anomaly reporting. N2 approval / A3 administration unchanged. Encoded in `Security.md` §3,
  `Phases.md` Phase 5, `Schema.md` §2.7, `ui-design.md` §5 — enforcement lands with Phase 5.
- `notifications.data` column converted to `jsonb` on PostgreSQL (Filament bell queries
  `data->>'format'`; text column crashed on pg, invisible on sqlite tests).

## Phase 1 — Identity, RBAC foundation & realtime bootstrap (2026-07-04)

### Added
- `faculties` and `services` tables + Filament resources (modal-based CRUD, French labels
  via `lang/fr/patrimoine.php`); `users` gains `faculty_id` and encrypted TOTP MFA columns.
- RBAC as data: `spatie/laravel-permission` v7 + `filament-shield` v4. The locked six-role
  matrix (`gestionnaire_patrimoine`, `responsable_faculte`, `rectorat`, `service_technique`,
  `tout_utilisateur`, `enseignant`) + technical `super_admin` seeded with **zero permissions**
  for business roles (least privilege; grants managed in the Shield UI). Shield's auto
  `panel_user` role disabled; super admin is gate-based (`Gate::before`).
- User management resource (roles multi-select, faculty, safe password handling; MFA columns
  never exposed in forms) + Shield-generated policies for User/Role/Faculty/Service.
- Auth hardening (Security.md §2/§5): `Password::defaults()` min 12 + breached-password check,
  Filament login rate limiting, Redis-backed panel-wide throttle (120/min/user),
  app (TOTP) MFA with recovery codes — **mandatory for A3/N2/N3/super_admin** via a request-time
  middleware (`EnsureElevatedRolesHaveMfa`), optional for everyone else — and a 30-min idle
  timeout for elevated sessions (`EnforceElevatedIdleTimeout`, configurable in `config/patrimo.php`).
- Realtime notification bootstrap (decision: **Laravel Reverb**, self-hosted, Pusher protocol —
  see Claude.md §3): `reverb` + `queue` containers, private per-user channel in
  `routes/channels.php`, throttled `/broadcasting/auth`, Echo + pusher-js wired into the panel,
  Filament database notifications with websocket refresh + 30s polling fallback, and a
  `patrimo:test-notification {email}` command proving the chain end to end.
- `spatie/laravel-activitylog` v5 on User/Faculty/Service models.
- Demo seeders (obviously fake): 2 faculties, 2 services, one account per role
  (`*@demo.ubma.dz` / password `password`).
- 16 Pest tests: role matrix panel access, denied cases (tout_utilisateur/enseignant vs user
  management), channel authorization (own OK / other's 403 / guest redirected), login lockout
  after 5 failures, MFA redirect for elevated roles, idle-timeout enforcement.

### Fixed
- `Phases.md` Phase 1 2FA role list corrected (stale "Responsable Finance" removed — not in
  the locked role set).

### Notes
- Realtime works locally out of the box (Reverb needs no external account). `TODO(confirm)`
  items remain: SLA business-day calendar, PAdES/N3 monetary threshold (unchanged).

## Phase 0 — Environment & project bootstrap (2026-07-03)

### Added
- Dockerized dev stack: nginx reverse proxy (`:8080`) → php-fpm 8.4 (non-root user, Node/npm
  included for asset builds) + PostgreSQL 16 + Redis 7. DB/Redis reachable only on the
  internal Docker network, never published to the host (`Security.md` §9).
- Laravel 12 scaffold at repo root; `.env`/`.env.example` wired to pgsql + Redis-backed
  cache, session and queue from day 0.
- Filament v4.11 admin panel at `/admin` with the brand theme extracted from the legacy app
  (`ui-design.md` §3/§4): custom teal palette anchored on `#004c4c`/`#0f766e`, Plus Jakarta
  Sans, squared-off radius scale (2/4/8/12px), teal scrollbar, UBMA logo + "Patrimo" wordmark.
- QA tooling: Pest 4 (with `/admin/login` smoke test + guest-redirect denial test),
  Larastan level 6, Pint. CI workflow running pint/phpstan/pest/`composer audit`/
  `npm audit`/Vite build.
- `legacy/Patrimo-BitHack/`: previous stack archived with intact git history (verified via
  `git fsck` + zero missing tracked files before the source leftover was removed).

### Notes
- Tests currently run on sqlite `:memory:` (skeleton default). Revisit when pg-specific
  features land (e.g. reservation overlap exclusion constraints in Phase 5) —
  `TODO(confirm)` in that phase whether CI adds a pgsql service matrix.
- Registration route: Filament login only; user provisioning arrives with RBAC in Phase 1.
