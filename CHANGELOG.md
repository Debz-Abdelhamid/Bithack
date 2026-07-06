# Changelog

All notable changes to the PUI-UBMA R13 Patrimoine module.
Format: one entry per phase (see `Phases.md`), Conventional-Commit-style categories.

## Phase 5 — Room reservations (2026-07-06)

### Changed (owner decision, reverses part of the 2026-07-04 policy)
- **Timetable authorship moved from teachers to the faculty**: the emploi du temps is now
  entered directly by **N2 (own faculty's rooms) or A3 (central/shared rooms)** as confirmed
  recurring slots, teacher picked from a dropdown — not requested by the teacher. Enseignant
  keeps **ad-hoc/one-off requests** (makeup classes, defenses, meetings) in whatever gaps
  remain, still routed through N2/A3 approval. Closed a routing gap this exposed: a
  central/shared room (no owning N2) now falls back to A3 for both timetable ownership and
  ad-hoc approval. Reflected across all five planning docs before implementation.

### Added
- `room_reservations` (Schema.md §2.7) with a **`source` enum** (`timetable`/`request`) and a
  `teacher_user_id` column distinct from `requested_by_user_id` (who entered the row vs. who
  it's for) — both additions beyond the original §2.7 listing, documented in Schema.md.
  `recurring_group_id` (uuid) ties a generated weekly series together for bulk identification.
- **Double-booking prevention, two layers**: a Postgres `EXCLUDE USING gist` constraint
  (`btree_gist`, partial `WHERE status = 'confirmed'`) — verified live by hand-inserting a
  raw overlapping row and watching Postgres reject it — plus a driver-agnostic
  `RoomReservationObserver` guard (throws before any confirmed overlap is saved, on every
  driver, including outside Filament's own forms).
- **Timetable entry** (`RoomReservationResource`, N2/A3 only — no `ViewAny`/`View` for
  Enseignant): single or **weekly-recurring** slots (generated as one row per week, sharing
  `recurring_group_id`; the whole series is pre-validated for conflicts before anything is
  persisted — one bad week blocks the entire series, never a partial save). N2's room picker
  is restricted to their own faculty's non-shared rooms; a scoped-exists form rule rejects a
  forged foreign/shared room id server-side even if the UI is bypassed.
- **`ReservationApprovalService`**: confirm (re-checks for a confirmed overlap that appeared
  in the meantime; auto-rejects other pending requests competing for the same room/time —
  PROGRESS.md open question #5's working default) / reject (optional reason) /
  notifyApprover (routes to the room's-faculty N2, or A3 for a central/shared room).
- **`RequestReservation`** custom page (Enseignant-only nav item — hidden from N2/A3, who
  already hold `Create:RoomReservation` for their own admin flow): campus-wide room search
  (their own `faculty_id` never filters the catalog), course-vs-non-course conditional
  validation (level required with a module; purpose required without one), attendees ≤ room
  capacity, per-user hourly rate limit (`PATRIMO_RESERVATION_REQUEST_MAX_PER_HOUR`, mirrors
  the registration per-IP throttle pattern), "my requests" list with self-service cancel.
- **`ReservationAvailability`** custom page: read-only weekly grid, every role, confirmed
  reservations only, deliberately bypasses FacultyScope (public campus information — same
  precedent as the Phase 2 campus map).
- Queued `SendReservationNotification` job (Security.md §7: payload-minimized — the stored
  notification is the source of truth, the broadcast is a content-free "refresh your bell"
  ping) for `request`-row events only, per Phases.md Phase 5 (`timetable` rows need no
  notification — authorship by N2/A3 *is* the approval).
- 25 new Pest tests (118 total): overlap detection/rejection, pending-pending coexistence,
  A3/N2 timetable creation, cross-faculty and shared-room denial (server-side), recurring
  series generation and all-or-nothing conflict handling, confirm/reject + auto-reject +
  race-condition re-block, ad-hoc submission/validation/rate-limit/cancel, page-nav gating,
  availability-grid content filtering.

### Fixed (found during testing, before any release)
- **Cross-faculty notification lookups silently failed**: `ReservationApprovalService` and
  `RequestReservation::myRequests()` dereferenced `$reservation->local->building` — but
  `Local` and `Building` carry their *own* `FacultyScope`, keyed to whoever is currently
  authenticated, not the reservation's owner. A Sciences-affiliated teacher booking a
  Technology room (explicitly allowed — campus-wide booking) would silently get a null
  building mid-request, breaking approver routing. Fixed with explicit
  `withoutGlobalScope(FacultyScope::class)` reads at both the `local` *and* nested `building`
  eager-load levels — a good reminder that bypassing a scope on the outer query doesn't
  bypass it on related models loaded through it.

### Notes
- Two conservative defaults **implemented but not yet confirmed with the university**
  (PROGRESS.md open question #5): recurrence capped at 4 months
  (`PATRIMO_RESERVATION_MAX_RECURRENCE_MONTHS`), and confirming a pending request
  auto-rejects other pending competitors for the same slot.
- Migration reversibility verified; the exclusion constraint requires `btree_gist`
  (auto-created via `CREATE EXTENSION IF NOT EXISTS`) — Postgres only, sqlite/tests rely on
  the observer.

## Phase 4 — Affectations (2026-07-06)

### Added
- `assignments` table (Schema.md §2.6): subject = equipment, whole room, or equipment moving
  into a room (permissive reading of §1's "affectation d'un bien à un service/local/personne";
  `TODO(confirm)` noted in Schema.md); targets = service and/or responsible person;
  `assigned_by_user_id` always taken from the authenticated session, never form input.
  Restrict-on-delete FKs, active-lookup indexes, and a Postgres CHECK constraint on the
  subject (verified rejecting subject-less rows).
- **AssignmentObserver** (Étape 2 / Phase 4 DoD): a new active assignment closes the previous
  active one on the same subject (end_date = new start — closed one by one so each closure is
  audit-logged, never deleted), and an equipment assignment carrying a destination room syncs
  `equipments.local_id` — "assigning an asset updates its current location/service and
  preserves full history".
- **AssignmentResource** (list w/ active/service filters, create, edit) + **Revoke** action
  (end_date = today, policy-checked, confirmation required) shared with the relation managers.
- **History on detail pages**: AssignmentsRelationManager on the Equipment view/edit page
  (the old app's "Affect" flow — equipment bound automatically, optional destination room)
  and a read-only variant on the Room page (whole-room + equipment-in-room history);
  "Current assignment" entry on the Equipment infolist.
- **RBAC**: A3 full CRUD; **N2 create/update within their faculty** (matrix: "approve for
  their faculty: affectations") — server-side scoped-exists rules block posting foreign
  asset ids even with a forged request; deletion stays A3-only (history preservation);
  N3 read-only; FacultyScope extended to Assignment via its subject.
- Demo assignment (desktop computer → Computer Science Laboratory, idempotent).
- 12 new Pest tests (93 total): create + assigner recorded, auto-close history, location
  sync, subject/target validation, N2 own-faculty create, N2 foreign-asset denial (server
  side), N2 list scoping + foreign 404, delete A3-only, tout_utilisateur denied, revoke
  closes-not-deletes, equipment-page history + current-assignment display.

### Notes
- Validation gotcha encoded: closure rules on nullable fields are skipped when empty, so the
  target-completeness rule lives on `start_date` (always validated).

## Phase 3 — Inventory: Equipments + QR (2026-07-05)

### Added
- `equipments` (Schema.md §2.3; `sub_category` relaxed to nullable — documented divergence),
  `qr_codes` (polymorphic, **opaque unique UUID token** — sequential ids never leave the
  system, closing the legacy guessable-code hole from ui-design.md §9.3) and
  `purchase_references` (R7 stub, §2.13) tables + enums, models, factories.
- **Étape 1 workflow** via `EquipmentObserver`: every equipment gets a unique inventory code
  (`UBMA-YYYY-NNNNN`, auto-generated when blank — manual entry kept for legacy registry
  numbers; best-effort row lock + unique index as the hard guarantee) and a QR token at
  creation; the QR row is cleaned up on delete.
- **Equipment Filament resource** (list w/ status/condition/category/room filters, create,
  edit, view) with a QR block on the view page (256px SVG via `simplesoftwareio/simple-qrcode`
  / BaconQrCode, same rendering stack look as the legacy `qrcode.react` block) + mono
  inventory codes; `PurchaseReference` manage-page stub + inline-create from the equipment
  form (full procurement module stays R7/Phase 10).
- **Print label** (old-app flow parity): new tab → A4 card (QR + monospace code) →
  auto `window.print()`; route is auth + `PrintLabel:Equipment` policy + throttle protected,
  marks the QR printed and audit-logs `label_printed`. Deliberate, documented GET side
  effect (synchronous new-tab navigation can't POST; flag is an idempotent operational marker).
- **Public QR lookup** `GET /report/{token}` (Phase 3 DoD): same URL contract as the legacy
  app so labels printed now survive Phase 6 (report form lands on this page). Public,
  read-only, **data-minimized** (designation, code, category, location, status only — no
  value/serial/notes/photo, Law 18-07), UUID route constraint, unknown = plain 404, and a
  Redis-backed `qr-lookup` limiter (30/min/IP + 10/min/token+IP) verified live (429 +
  Retry-After).
- **FacultyScope extended to Equipment** (room → building → faculty): N2 sees own-faculty +
  shared-building + unplaced/central-stock assets; foreign detail URLs 404. A3 full CRUD +
  PrintLabel; N2/N3 read-only (PermissionSeeder baseline).
- Demo data: 1 purchase reference + 4 equipments (fixed codes for idempotent re-seeding,
  QR tokens via the observer).
- 22 new Pest tests (81 total, sqlite + pgsql): code/token generation (sequence, manual-code
  continuation, uniqueness), QR cleanup on delete, A3 create happy path, N2 create denied +
  scoping + foreign 404 + read-only view, tout_utilisateur denied, view-page QR render,
  public lookup 200/404/junk-404, sensitive-field leak guard, per-token 429, print-label
  A3 ok + audit / N2 403 / guest redirect, purchase reference create + link.

### Notes
- Migration reversibility verified (`migrate:rollback --step=3` + re-migrate) and
  `migrate:fresh --seed` gated on a **scratch database** — the live dev DB was only
  additively migrated.

## Phase 2 — Buildings & Rooms + campus map port (2026-07-05)

### Added
- `buildings` (+ `faculty_id`, a documented Schema.md divergence — required by N2 scoping,
  Phase 5 approval routing and the map's data contract; NULL = central/shared) and `locals`
  tables, enums, factories, and full Filament resources (Building with a Rooms relation
  manager; Rooms standalone), all eager-loaded/counted per Schema.md §5.
- **FacultyScope** (Security.md §3): faculty-bound users (N2) see only their faculty's
  buildings/rooms plus shared ones via an Eloquent global scope; A3/N3/admin unscoped;
  ungranted `ViewAcrossFaculties` escape hatch; the campus map and future room catalog
  deliberately bypass it (public campus, campus-wide teacher booking).
- **Campus map ported as-is** from Patrimo-BitHack (ui-design.md §6): `maplibre-gl` v5
  (React wrapper dropped), OpenFreeMap bright tiles, UBMA center @ zoom 17 / pitch 45,
  same controls, same custom SVG flag markers + hover tooltips + selected state, rooms
  side panel on click, and the crosshair pick-a-location mode — wired through the Building
  update policy (never a raw write).
- `PermissionSeeder`: matrix-baseline grants (A3 full Building/Local CRUD; N2/N3 read-only;
  campus map viewable by every role — the physical campus is public).
- Demo campus: 3 buildings (Technology, Sciences, shared library) with 7 rooms around the
  real UBMA coordinates.
- 10 new Pest tests (59 total): A3 create building+room, N2 scoping (list + 404 on foreign
  detail + create denial), A3 unscoped, tout_utilisateur resource denial vs map access,
  teacher full-campus map payload, picking denied (teacher) / allowed+persisted (A3).

### Notes
- `migrate:fresh --seed` was run as part of the DoD gate — local demo DB reset (demo accounts
  re-seeded; self-registered accounts/MFA setups need re-creating).

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
