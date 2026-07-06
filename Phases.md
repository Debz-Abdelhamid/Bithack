# PHASES.md — Build plan for R13 "Patrimoine" (Laravel + Filament)

General rule: **no phase starts before the previous one is "Done"** per the generic
Definition of Done in `Claude.md` §8. Every phase must independently satisfy the
Security gate and Performance gate described there.

---

## Phase 0 — Environment & project bootstrap
**Goal:** a running, empty Laravel + Filament app, fully dockerized, with the old project archived (not deleted) for design reference.
- Inspect `Patrimo-BitHack`, complete the extraction checklist in `ui-design.md` §2.
- Archive old repo (`legacy/` folder or separate git branch/tag) — confirm with the user before any destructive delete.
- `docker-compose.yml`: `app`, `postgres`, `redis`, reverse proxy.
- Fresh Laravel install, Filament installed, base Panel Provider configured with theme skeleton from `ui-design.md` §4.
- CI pipeline skeleton: Pint, Larastan, Pest, running inside Docker/GitHub Actions.
- **Definition of Done:** `docker compose up` gives a working `/admin` login screen matching the base theme tokens.

## Phase 1 — Identity, RBAC foundation
**Goal:** authentication + full role/permission scaffolding, including the roles identified as missing (see `Security.md` §3).
- `users`, `faculties`, `services` tables.
- `spatie/laravel-permission` + `filament-shield` installed and configured.
- Seed the role matrix (A3, N2, N3, Service technique, Tout utilisateur, Enseignant) as data, not enums.
- 2FA enabled for elevated roles (Gestionnaire A3, Responsable faculté N2, Rectorat N3, plus the technical super admin) — per `Security.md` §2. *(Corrected 2026‑07‑04: an earlier draft listed a "Responsable Finance" role that does not exist in the locked role set.)*
- Login throttling in place from day one (`Security.md` §5).
- Notification infrastructure bootstrap (realtime = notifications only, per `Claude.md` §3):
  Laravel database notifications + Broadcasting over Laravel Reverb (self‑hosted, Pusher
  protocol); private per‑user channel
  (`App.Models.User.{id}`) authorized in `routes/channels.php`; Laravel Echo wired into the
  Filament panel (`->databaseNotifications()` with websocket‑triggered refresh instead of
  polling); broadcast events queued on Redis. Later phases add their own notification events
  on top of this — no new channel types without a `Security.md` review.
- **Definition of Done:** every role in the matrix can log in and sees a panel restricted to only its permitted resources (even though those resources don't exist yet — permission checks return "no access" cleanly); a test notification sent to a user shows up in their Filament notification bell in realtime, without a manual refresh.

## Phase 2 — Referential data: Buildings & Locals
**Goal:** `buildings`, `locals` fully manageable.
- Filament Resources + Policies.
- Cartographie campus: **port the existing interactive map from `Patrimo-BitHack` as‑is** (same
  map library, same interaction behavior, same underlying data shape) rather than a placeholder
  or a redesign — identify the exact map tech during the Phase 0 extraction pass and carry it
  over. Full data wiring (all buildings/locals plotted) can land in Phase 11 once that data exists,
  but the component itself should already be the ported one, not a stand‑in.
- **Definition of Done:** A3 can create a building → add locals to it; N2 sees only locals under their faculty scope (global‑scope or policy‑enforced query); the campus map renders using the ported component.

## Phase 3 — Inventory: Equipments + QR
**Goal:** Étape 1 of the workflow — "Bien enregistré avec ID unique, QR code généré."
- `equipments`, `qr_codes` tables; QR generation on creation using an **opaque token**, not the numeric id.
- "Print label" action producing a scannable QR + inventory code.
- Purchase reference stub (`purchase_references`) linking toward R7 (interface only if R7 isn't built yet).
- **Definition of Done:** creating an equipment always yields a unique inventory code and a scannable QR; QR resolves to a public, rate‑limited, read‑only lookup endpoint.

## Phase 4 — Affectations
**Goal:** Étape 2 — "Affectation enregistrée avec date et responsable."
- `assignments` table + Resource, scoped by A3/N2 permissions.
- History view on the equipment/local detail page (who had it, when).
- **Definition of Done:** assigning an asset updates its current location/service and preserves full history.

## Phase 5 — Room reservations
**Goal:** Étape 3 — booking with live availability, confirmation, and R9 calendar link.
- `room_reservations` table, overlap‑prevention logic (across both kinds below), calendar UI
  (see `ui-design.md` §5).
- **Two reservation kinds** (`source` enum, `Schema.md` §2.7) — *(decision 2026‑07‑06,
  reversing the 2026‑07‑04 "Enseignant books incl. recurring slots" policy)*:
  - **`timetable`** — the faculty's emploi du temps. **N2 enters/edits their own faculty's
    recurring course slots directly as `confirmed`** (rooms visible under their FacultyScope,
    excluding shared ones); **A3 does the same for central/shared rooms**. The teacher is
    picked from a dropdown of Enseignant accounts, never typed — this is how "each faculty
    fills her timetable first" happens, before any ad‑hoc booking has anywhere to go.
  - **`request`** — **Enseignant‑initiated ad‑hoc/one‑off bookings** (makeup classes,
    defenses, meetings) in whatever gaps remain. Starts `pending`; approved by the N2 of the
    **room's** faculty, or **A3 when the room is central/shared** (no N2 owns it — gap closed
    2026‑07‑06, see `Security.md` §3). `tout_utilisateur` still gets a **read‑only** view of
    the combined grid — no booking form for either kind.
- **Teachers search/request campus‑wide for ad‑hoc bookings** — their own `faculty_id` never
  filters the catalog (affiliation metadata only). *(Clarified 2026‑07‑05, see `Security.md` §3.)*
- **Request form fields (owner requirement 2026‑07‑05):** requester name/faculty shown
  read‑only from the authenticated account (never typed) for `request` rows; module name +
  level (L1…Doctorate) required for course bookings on either kind; department (free text
  until R9); optional student group; expected attendees **validated against room capacity**;
  date/time or weekly recurrence; optional purpose/notes for non‑course bookings. See
  `Schema.md` §2.7.
- Rate limit the `request` booking endpoint to prevent slot‑spamming — `timetable` entry is
  an authenticated N2/A3 panel action, not a public‑facing high‑burst path.
- Realtime notifications (database + broadcast, queued), **`request` rows only**: requester
  notified when their reservation is confirmed/rejected; approver (room's‑faculty N2, or A3
  for shared rooms) notified of new pending requests. No notification needed for a
  `timetable` row — N2/A3 authored it directly as confirmed.
- **Definition of Done:** two users cannot double‑book the same room/time regardless of
  `source`; N2 (or A3 for central/shared rooms) can enter/edit their timetable directly and
  can confirm/reject pending ad‑hoc requests within their scope.

### Phase 5 addendum (2026‑07‑06, owner-requested)
- **`departments`** (belongs to a faculty) and **`academic_terms`** (year split into 2
  semesters) become real referentials — a faculty manages several departments and fills each
  one's timetable one term at a time. N2 manages their own faculty's departments; A3 manages
  every department and owns the university‑wide `academic_terms` list.
- **Visual timetable grid** (`TimetableBuilder` page) — N2/A3 pick a department + academic
  term and fill a weekly grid (6 fixed periods × Sat–Thu columns, ported from the legacy
  app's exact seed data) instead of a plain form list; the plain `RoomReservationResource`
  create form still works underneath (shared `TimetableSlotService`) but the grid is the
  primary entry point. A weekly slot's recurrence now runs to its Academic Term's `end_date`
  — the arbitrary "repeat until" date + month cap is gone.
- **Definition of Done (addendum):** a faculty's timetable is visibly organized by department
  and academic term; entering a slot from the grid produces the same term‑bound weekly series
  as the plain form; N2 cannot fill another faculty's department's timetable.

## Phase 6 — Anomaly reporting → automatic ticket creation
**Goal:** Étape 4 — "Scan QR du bien → ticket créé automatiquement."
- Public (authenticated, but lightweight/mobile‑first) scan endpoint resolving `qr_codes.token` → prefilled ticket form → `maintenance_tickets` row created with `source = qr_scan`, priority defaulted per category rules.
- Heavy rate limiting + abuse protection here specifically (this is the most "public-facing, high burst" endpoint in the whole module — see `Security.md` §5).
- Realtime notifications (database + broadcast, queued): new `qr_scan` ticket notifies A3 and
  the routed Service technique — ping carries ids/labels only, never the report free text
  (`Security.md` §7 payload rules).
- **Definition of Done:** scanning a real printed QR from a phone creates a ticket with the correct SLA in under the throttle limits, and cannot be trivially spammed to flood the ticket queue.

## Phase 7 — Maintenance ticket workflow & SLA engine
**Goal:** Étape 5 — "Intervention planifiée — SLA 48h (urgent) / 5j (standard)."
- `interventions` table, assignment to a Service technique member (`technician_id` on `interventions`
  stays a plain `users` FK — no separate role needed, just a user who belongs to the Service
  technique group), status transitions with a proper state machine (avoid ad‑hoc string checks).
- Queued job computing `sla_due_at` and escalating tickets approaching/breaching SLA (notify A3/N2
  via database + broadcast — the realtime bell is what makes "without manual polling" in the DoD
  real). Technician (Service technique member) notified when an intervention is assigned to them.
- **Ticket board is a drag‑and‑drop Kanban view, matching the old `Patrimo-BitHack` UI** — columns
  = ticket status (`new → assigned → in_progress → resolved → closed`), dragging a card between
  columns triggers the same state‑machine transition as any other status change (never bypass the
  transition rules just because the move came from a drag event). Evaluate a Filament Kanban
  plugin (e.g. `mokhosh/filament-kanban`) first; fall back to a custom Livewire board only if the
  plugin can't match the old app's exact behavior — check the old repo's Kanban implementation
  (library, drag library, column config) during the Phase 0 extraction pass.
- **Definition of Done:** SLA deadlines are computed automatically and correctly; an approaching/breached SLA visibly escalates (notification + dashboard flag) without manual polling; drag‑and‑drop status changes are permission‑checked (a user can't drag a ticket into a status they're not allowed to set) and enforce the same validation as a manual status change.

## Phase 8 — Ticket closure, cost, PV de réception (PAdES)
**Goal:** Étape 6 — "Bien remis en service, historique mis à jour," including PAdES signature above the (to‑be‑confirmed) monetary threshold.
- Closure form: report, cost, restores equipment `status = in_service`.
- `reception_reports` table; PAdES signing flow for N2 (standard) / N3 (grands travaux).
- Realtime notification (database + broadcast, queued): the responsible N2/N3 signer is notified
  when a closure awaits their PV signature — ids/labels only, no amounts in the broadcast payload
  (`Security.md` §7).
- **Definition of Done:** closing a ticket above threshold blocks completion until a validly signed PV is attached; below threshold, closure works without that step.

## Phase 9 — Regulatory controls
**Goal:** interface module 5 — "Contrôles réglementaires."
- `regulatory_controls` table, due‑date tracking, certificate upload, non‑compliance flagging.
- Owned by A3 (Gestionnaire patrimoine), consistent with the source doc's interface module 5 ("Patrimoine Immobilier: Cartographie campus, Locaux, Contrôles réglementaires") — no separate compliance role.
- Scheduled job notifies A3 of upcoming/overdue controls and non‑compliant results
  (database + broadcast; this one is cron‑driven, not event‑driven).
- **Definition of Done:** upcoming/overdue controls are visible on the dashboard; non‑compliant results are clearly flagged and cannot be silently ignored.

## Phase 10 — Finance & procurement integration (R7, R10)
**Goal:** `maintenance_budgets`, `purchase_references` become real integration points, not stubs.
- Approval stays inside the existing N2/N3 hierarchy (N2 for standard costs, N3 above the
  to‑be‑confirmed threshold — same threshold logic as the PAdES rule in Phase 8), no separate
  finance role.
- If R7/R10 modules already exist as services, integrate via internal API; otherwise keep as a well‑isolated interface so it's a drop‑in later.
- Realtime notification (database + broadcast, queued): N2/N3 approver notified when a budget
  is submitted for approval; requester notified of the decision. No amounts in broadcast payloads.
- **Definition of Done:** maintenance costs above the confirmed threshold cannot be recorded without an N2/N3 budget approval.

## Phase 11 — Dashboards, interactive campus map, reporting/exports
- KPI widgets (open tickets by SLA state, upcoming reservations, upcoming controls, budget consumption).
- Full data wiring for the campus map ported in Phase 2 — every building/local plotted, same
  interactions (zoom, click‑through to a local's detail) as the old app.
- CSV/PDF export actions on the main lists.
- **Definition of Done:** each declared role sees a dashboard scoped to what it's responsible for.

## Phase 12 — Security hardening pass
- Full checklist in `Security.md` §12 re‑verified end‑to‑end, dependency audit, headers, CSP, session hardening.
- **Definition of Done:** checklist fully green; no `TODO(confirm)` left unresolved without an explicit decision logged.

## Phase 13 — Performance & load testing
- Load test the QR‑scan endpoint, login, and room‑reservation endpoint specifically (the three
  most "many concurrent users at once" paths described by the university).
- Tune caching, queue worker counts, DB indexes based on results; consider Laravel Octane if
  sustained concurrency requires it.
- **Definition of Done:** documented load test results against an agreed target (e.g., N concurrent
  users, response time budget) with no 5xx errors and no unbounded queue backlog.

## Phase 14 — UAT, docs, production deployment
- Production `docker-compose.prod.yml` (or equivalent), HTTPS termination, backups configured.
- User documentation per role, admin runbook.
- **Definition of Done:** sign‑off from at least one real A3/N2 user after a guided UAT session.
