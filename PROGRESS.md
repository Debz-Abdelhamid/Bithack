# PROGRESS.md — Build state & decision log

> **For any AI agent/developer resuming work:** read `Claude.md`, `Schema.md`, `ui-design.md`,
> `Phases.md`, `Security.md` first (in that order), then this file. Update this file at the end
> of every phase. Never start a phase before the previous one's Definition of Done + Security
> gate passed and the project owner said go.

**Repo:** https://github.com/Debz-Abdelhamid/patrimo (private, branch `main`, CI must stay green)
**Last update:** 2026-07-06 — Phases 0–4 complete · next: **Phase 5 (Room reservations)** — waiting for owner go

---

## Where we are

| Phase | Status | Highlights |
|---|---|---|
| 0 — Bootstrap | ✅ | Docker stack (nginx :8080 → php-fpm 8.4 non-root, PostgreSQL 16, Redis 7, queue, reverb, mailpit :8025, daily backup sidecar). Filament v4 panel themed from the legacy `Patrimo-BitHack` extraction (teal `#004c4c`/`#0f766e`, Plus Jakarta Sans, squared radii, UBMA brand). Legacy app archived in `legacy/` (own git history, git-ignored). QA: Pest + Larastan L6 + Pint; CI runs everything twice (sqlite + PostgreSQL). |
| 1 — Identity & RBAC | ✅ | Locked six roles + technical `super_admin` as spatie/shield **data** (zero-permission default, gate-based super admin, Shield `panel_user` disabled). TOTP MFA **enforced for A3/N2/N3/admin** (request-time middleware), 30-min elevated idle timeout, login lockout, Redis panel throttle (120/min). Realtime notifications via **Laravel Reverb** (ws://localhost:8081, private per-user channels, throttled `/broadcasting/auth`, queued). Domain-restricted **self-registration** (`PATRIMO_REGISTRATION_DOMAINS`, default univ-annaba.dz) + mandatory email verification (Mailpit locally) + hourly IP cap; password reset; email-change verification; institutional-domain rule on **all four** email surfaces (register, profile, admin create/edit — unchanged emails grandfathered). Account deactivation (`is_active`). Audit log: auth events (`login`/`logout`/`login_failed`/`password_reset`) + RBAC changes (role/permission attach/detach + Role lifecycle) + model changes (users/faculties/services). |
| 2 — Buildings & Rooms + map | ✅ | `buildings` (+`faculty_id` — documented Schema.md §2.1 divergence; NULL = central/shared) & `locals` with enums/factories/resources (Building has a Rooms relation manager). **FacultyScope** global scope: N2 sees own faculty + shared only (list, search, direct URL → 404); A3/N3 unscoped; `ViewAcrossFaculties` escape hatch exists ungranted. **Campus map ported as-is**: `maplibre-gl` v5 vanilla (React wrapper dropped), OpenFreeMap bright tiles, UBMA center zoom 17 / pitch 45, same SVG flag markers/tooltips/selection, rooms side panel, crosshair pick-a-location **through the Building update policy**. `PermissionSeeder` = matrix baseline (A3 full CRUD, N2/N3 read-only, map viewable by all roles). |
| 3 — Equipments + QR | ✅ | `equipments` (§2.3, `sub_category` relaxed nullable — documented) + `qr_codes` (**opaque UUID token**, unique morph pair) + `purchase_references` R7 stub. `EquipmentObserver`: auto inventory code `UBMA-YYYY-NNNNN` (manual entry kept; unique index = hard guarantee) + QR row on create, cleanup on delete. Equipment resource (filters, view page w/ 256px QR SVG block via simple-qrcode). **Print label**: A3-only (`PrintLabel:Equipment`), new tab → A4 → auto print, marks printed + audit (documented GET side effect). **Public lookup `GET /report/{token}`** — same URL contract as legacy so printed labels survive Phase 6; data-minimized card (no value/serial/notes/photo); UUID route constraint; Redis `qr-lookup` limiter (30/min/IP + 10/min/token+IP) verified live. FacultyScope → Equipment via room→building (unplaced = central, visible). N2/N3 read-only, tout_utilisateur denied. |

| 4 — Affectations | ✅ | `assignments` (§2.6): subject = equipment / whole room / equipment→room (permissive §1 reading, `TODO(confirm)` in Schema.md); targets service/person; `assigned_by` from session only; pg CHECK on subject. **AssignmentObserver**: new active assignment auto-closes the previous one (audit-logged, never deleted) + destination room syncs `equipments.local_id` (DoD: "updates current location/service, preserves history"). Resource + **Revoke** action (end today, policy-checked); history relation managers on Equipment (create w/ auto-bound subject, old "Affect" flow) + Room (read-only); current assignment on the Equipment infolist. RBAC: A3 full; **N2 create/update own faculty** (scoped-exists rules beat forged ids); delete A3-only; N3 read. |

**Tests:** 93 green (sqlite + pgsql in CI). **English-first UI** (owner decision; `lang/en` primary, `lang/fr` maintained). **Timezone:** Africa/Algiers.

## Decision log (owner decisions, dated — do not re-litigate)

- **2026-07-04** Realtime = **Reverb** (self-hosted, Pusher protocol), notifications only, minimal payloads.
- **2026-07-04** **Booking initiation = Enseignant only**; `tout_utilisateur` = read-only timetable + QR reporting. N2 approves, A3 administers.
- **2026-07-04** Self-registration: institutional domains only, verified, throttled, auto `tout_utilisateur`. Provisioned accounts pre-verified.
- **2026-07-05** English-first UI (reversal of French-first; strings stay in lang files). Timezone Africa/Algiers.
- **2026-07-05** `faculty_id` semantics: **N2 = required authorization boundary** (form-enforced); teacher/user = affiliation only, never filters rooms; empty = central. **Approval routes to the ROOM's faculty N2**, not the requester's.
- **2026-07-05** Phase 5 form fields: requester read-only from account; module + level required for course bookings; department free-text until R9; attendees ≤ room capacity (Schema.md §2.7).

## Open questions (`TODO(confirm)` — never guess)

1. SLA business-day calendar (holidays/weekends) for "standard ≤ 5j" (Schema.md §6).
2. Monetary threshold triggering N3 approval + PAdES signature (Phases 8/10).
3. Definitive registration domain list (student subdomains?) — `PATRIMO_REGISTRATION_DOMAINS`.
4. Department field → FK when R9 academic referential exists.
5. Phase 5 defaults awaiting confirmation: recurrence ends at teacher-picked date (≤ ~4 months); N2 sees all conflicting pending requests, confirming one auto-rejects overlaps.
6. Affectation semantics (Phase 4, Schema.md §2.6): an equipment assignment with a destination room also moves the asset (`equipments.local_id` synced) — permissive reading of §1; confirm whether affectation and physical relocation should be strictly separate operations instead.

## Operational notes

- Demo accounts: `admin@ / a3@ / n2@ / n3@ / technique@ / enseignant@ / utilisateur@ demo.ubma.dz`, password `password`. Elevated roles forced into MFA setup on login.
- Everything runs in Docker only — never `php artisan serve` on the host. App: http://localhost:8080/admin · Mail: :8025 · WS: :8081.
- Composer/npm/artisan run **inside** the container (`docker compose exec app …`); vendor/node_modules live in named volumes.
- After any `.env` change: `docker compose restart queue reverb`.
- **Never `migrate:fresh` the live dev DB** without asking — use a scratch database for fresh-seed gates.
- Gates before every push: `pint --test`, `phpstan analyse --memory-limit=1G`, `pest` — then push and watch CI (`gh run watch`).
- Per phase: end with a report (built / DoD mapping / Security.md §12 checklist), update CHANGELOG.md + this file, wait for owner's go.

## Next

**Phase 5 — Room reservations** (Phases.md): `room_reservations` + overlap prevention
(pg exclusion constraint or app-level check), weekly timetable UI (ported grid),
**Enseignant-only booking** (campus-wide catalog — `FacultyScope` deliberately bypassed),
approval routed to the **room's** faculty N2, request-form fields per Schema.md §2.7
(requester read-only, module + level required for course bookings, attendees ≤ capacity),
booking-endpoint rate limit, realtime notifications (requester + approver). Open defaults
to confirm before/while building: recurrence end rule and conflicting-pending handling
(PROGRESS "Open questions" #5). **Waiting for owner go.**
