# Changelog

All notable changes to the PUI-UBMA R13 Patrimoine module.
Format: one entry per phase (see `Phases.md`), Conventional-Commit-style categories.

## Post-Phase-7 hardening — seeding, RBAC audit, room equipment print (2026-07-08/09)

Not a new phase — cleanup/fixes discovered while the owner ran manual verification of
Phases 1–7, plus one new feature requested during that pass.

### Fixed — timetable double-booking model was wrong (owner-reported, 2026-07-09)
- **A grid cell could only ever show one class.** The owner reported that filling the same
  period twice (e.g. two 08:00–09:30 Monday classes for two different groups) made the
  second card silently overwrite the first. Root cause: `TimetableBuilder::getGridSlots()`
  keyed reservations into a `"{day}-{period}"` array whose value was a single reservation —
  a second class for the same cell just clobbered the array entry. A department genuinely
  runs several parallel classes in the same slot (different rooms, different student groups),
  so the cell value is now a *collection* and the blade renders every card stacked.
- **The conflict rule now has two independent axes, not one.** Previously "can this slot be
  booked" only asked "is the room free" (`hasConfirmedOverlap`). That wrongly rejected a
  legitimate parallel class in a *different* room. Added `RoomReservation::
  hasConfirmedGroupOverlap()` as a second, separate check: the same **(department, level,
  student group)** can't be in two confirmed classes at once *even across two different
  rooms* — a class is "these students are busy", not only "this room is busy". A booking is
  rejected if it hits **either** axis; a different room AND a different group at the same
  day/time is allowed. Blank/unnamed student groups never conflict on the group axis (an
  unnamed group can't be safely matched — `TODO(confirm)` whether whole-cohort bookings
  should conflict with each other). Enforced in all three writers that share the rule:
  `TimetableSlotService` (series pre-validation), `RoomReservationObserver` (driver-agnostic
  guard under every save), and `ReservationApprovalService::confirm()` (friendly block on the
  ad-hoc approval path, new `confirm_blocked_group` message).
- **Added Level + Student-group filters to the grid** so a department with many parallel
  classes can narrow the view to one level/group at a time instead of reading stacked cards.
  The group filter is a free-text `<datalist>` seeded from the groups already used in that
  department/term.
- 5 new Pest tests (172 total): group-overlap detection (incl. the blank-group no-op),
  two parallel classes both rendering in one cell, a same-group-different-room series being
  refused, the approval-path group block, and the group filter narrowing the grid.

### Added
- `UserSeeder`: replaces `DemoSeeder` in the default `DatabaseSeeder` chain. 3 faculties
  (FT/FS/FM) + 13 users, all `@univ-annaba.dz`, password `password`. Elevated roles are
  seeded **without** a pre-set `app_authentication_secret` so MFA enrollment happens for
  real on first login, matching how the university's own users will onboard.
  `PermissionSeeder` is no longer in the default chain either — the owner assigns
  permissions to roles manually from the Shield UI using the Super Admin account
  (gate-based, so it works even with zero permission rows in the DB).
- `ReferenceDataSeeder` (opt-in, run manually): buildings/rooms (folds in the owner's own
  manually-created "BAT-INF" building plus 3 new ones), 12 equipment items, 4 services,
  1 assignment, 3 departments, 1 confirmed academic term + timetable reservation, 4
  maintenance tickets across different statuses/priorities with 3 interventions. Lets the
  owner verify Phases 3–7 against realistic data instead of creating everything by hand.
- **Room equipment print list** (owner request): `GET /locals/{local}/equipment-list`
  (`LocalEquipmentListController`) — an A4 printable page (auto `window.print()` on load)
  listing every `Equipment` row in a room with a pluralized count, reachable from a new
  "Print equipment list" row action on the Locals table (policy-checked, opens in a new
  tab). Respects `FacultyScope` at the route-model-binding level — an N2 opening another
  faculty's room gets a 404 before the controller runs, same as every other scoped
  resource. Added the missing `Local::equipments(): HasMany` relation this needed (and
  that the print feature's own verification also needed). 5 new Pest tests (167 total).

### Fixed (real bugs found during the owner's manual test pass, not guessed)
- **MFA enrollment QR code rendered as a broken image.** Root cause: the PHP image was
  missing the `imagick` extension. `pragmarx/google2fa-qrcode` already returns a complete
  `data:image/...;base64,...` URI, but Filament's `AppAuthentication::generateQrCodeDataUri()`
  has a fallback path that assumes imagick's absence means the renderer returned raw SVG
  needing a manual data-URI wrap — it doesn't, so the fallback double-base64-encoded an
  already-complete URI. Fixed by adding `imagick` to `docker/php/Dockerfile` and rebuilding
  all 4 PHP service images (`app`/`queue`/`reverb`/`scheduler` each build their own tagged
  image from the same Dockerfile — none share an `image:` key, so all 4 needed rebuilding).
- **`Faculty` and `Service` were never governed by `PermissionSeeder`** — a real RBAC gap
  found via systematic audit (comparing `FilamentShield::getResources()` against the
  seeder's coverage), not a cosmetic one: A3 had zero ability to manage either model, which
  would have blocked the owner's own Phase 4 manual testing (creating a Service). Added
  both to the `$fullPatrimoine` loop and their `ViewAny`/`View` to the read-only set; also
  switched every role's grant from `givePermissionTo()` to `syncPermissions()` so re-running
  the seeder is idempotent and can't accumulate stale permissions across edits.
- **Shield's role-edit UI wasn't showing `Intervention` or the app's 5 non-CRUD policy
  abilities** (`PrintLabel`, `ManageTimetable`, `Approve`, `Cancel`, `LogWork`) — `Interven
  tion` has no standalone Filament Resource by design (RelationManager-only, nested on
  `MaintenanceTicketResource`), so Shield never auto-discovered its permissions. Enabled
  `config('filament-shield.shield_resource.tabs.custom_permissions')` (disabled by default)
  and populated it with the 12 `Intervention` CRUD strings + the 5 custom abilities.
  Deliberately did *not* add `View:CampusMap`/`View:ReservationAvailability` here even
  though they're also "custom" in spirit — confirmed live via `FilamentShield::getPages()`
  that Shield already auto-discovers those correctly under the "Pages" tab, so adding them
  to `custom_permissions` too would have created visible duplicates.

### Notes
- Confirmed (owner asked directly): Shield's role-edit tabs — Resources / Pages / Widgets /
  Custom Permissions — are all cosmetic groupings over one flat permission list; there is no
  real section-grouping capability in Shield's stock UI (verified by reading
  `HasShieldFormComponents.php`/`HasResourceHelpers.php`, both flatten every tab into a
  single `CheckboxList`).
- Confirmed (owner asked directly): Super Admin showing **0** permission rows is correct,
  not a bug — `define_via_gate: true` + `intercept_gate: 'before'` in
  `config/filament-shield.php` means the gate short-circuits before any permission lookup
  runs, for anyone holding the `super_admin` role. Verified live via
  `$admin->can('AnythingAtAll:Whatever')` returning `true` with zero rows in `permissions`.

## Phase 7 — Maintenance ticket workflow & SLA engine (2026-07-08)

### Added
- `interventions` (Schema.md §2.9): nullable `technician_id` (a plain `users` row belonging
  to Service technique, no dedicated role), `scheduled_at`/`completed_at`, `report`, `cost`,
  its own `InterventionStatus` (independent of the parent ticket's status).
- `TicketStatus` gets a real state machine: `canTransitionTo()`/`next()` implement the linear
  `new → assigned → in_progress → resolved → closed` chain (matching both Phases.md's column
  order and the legacy `TicketDetailView.tsx`'s own `NEXT_STATUS` map, read before building),
  plus `cancelled` as a side branch reachable from any non-terminal status. `TicketWorkflowService`
  is the *only* place a ticket's status is ever written — the Kanban board's drag and the
  ticket page's "Advance status"/"Cancel ticket" actions both call it, so a drag can never
  bypass a rule a manual change would enforce. The plain Edit form's `status` field is disabled
  after creation for the same reason (helper text points to the board/actions instead).
- `MaintenanceBoard`: a custom Filament Page, native HTML5 drag-and-drop (Alpine
  `x-on:dragstart`/`dragover`/`drop`), 5 columns (`TicketStatus::boardColumns()`, `cancelled`
  excluded — still visible in the plain resource table). Cards show reference, priority badge,
  asset/room, SLA countdown or overdue flag, assigned technician. Drag-affordance and the move
  itself are both gated on `Update:MaintenanceTicket`.
- `InterventionsRelationManager` on `MaintenanceTicketResource`: A3 assigns technician/schedule
  (full CRUD); the assigned technician later logs their own report/cost/completion via a new
  `logWork` policy ability that compares `technician_id` to the acting user, not a role check
  (`Claude.md` §4 — policies read permissions/data, never `hasRole()`).
- `patrimo:escalate-tickets` (new `scheduler` Docker service running `schedule:work`, every 15
  minutes — the project's first scheduled job): notifies A3 + the ticket's routed N2 once per
  ticket (`maintenance_tickets.escalated_at` idempotency guard) when a ticket is ≥80% through
  its SLA window or past it. The "80% elapsed" check runs in PHP, not SQL — `sla_due_at -
  created_at` interval arithmetic isn't portable between Postgres and the sqlite test driver.
- Queued `SendTicketNotification` to a technician when assigned to an intervention.
- RBAC: Service technique moves from Phase 6's read-only to real write access — `Update:
  MaintenanceTicket` (drag/advance a ticket) and `LogWork:Intervention` (their own assignments
  only). A3 keeps full intervention CRUD.
- 15 new Pest tests (162 total): state-machine transitions (valid + rejected, including the
  no-reopen-after-closed rule), Kanban drag success/permission-denied/invalid-move, intervention
  assignment + technician notification, `logWork` scoping (own vs. someone else's), SLA
  escalation (breached, approaching-but-not-breached, and the resolved/closed/cancelled
  exclusion), idempotent re-run.

### Fixed (flagged, not guessed)
- Phases.md asks to evaluate `mokhosh/filament-kanban` before building custom — it requires
  Filament `^3.0`, no official v4 support (this app's version); only an unofficial third-party
  fork claims v4 compatibility. Read the legacy board's actual source (`TicketsBoardView.tsx`)
  rather than assume — it turned out to be plain native HTML5 drag-and-drop with no client
  library at all, so building custom lost nothing of substance versus depending on an
  unofficial fork for a security-sensitive university system.
- Two Filament testing gotchas hit while writing tests, worth remembering: (1) a policy method
  written for a *specific record* (`update(AuthUser $user, Model $record)`) breaks with "too
  few arguments" if called with a class-string subject instead of an instance (`can('update',
  SomeModel::class)`) — that call shape routes to a method taking only the user, like
  `create()`; a general "can this role update anything of this class" check needs the raw
  permission string, not the per-record policy method. (2) A relation manager's table header
  action (e.g. `CreateAction`) must be tested with `callTableAction()`/
  `assertHasNoTableActionErrors()`, not the generic `callAction()`/`assertHasNoActionErrors()`
  — the latter looks in the wrong action registry and reports the action as simply not
  existing, which reads like a permissions bug but isn't one.

## Phase 6 — Anomaly reporting → automatic ticket creation (2026-07-07)

### Added
- `maintenance_tickets` (Schema.md §2.8): `equipment_id`/`local_id` nullable subject pair with
  the same "at least one required" CHECK constraint pattern as `assignments`; a new `reference`
  column (`TCK-YYYY-NNNNN`, same auto-generation pattern as `equipments.inventory_code`) not in
  the original table sketch; `category` made nullable (documented divergence — see below).
  `TicketSource`/`TicketPriority`/`TicketStatus` enums, the last driving Phase 7's future
  Kanban columns.
- `MaintenanceTicketObserver`: computes `sla_due_at` at creation (`+48h` urgent / `+5 business
  days` standard, skipping Friday — Schema.md §4), assigns the reference, and auto-fills
  `assigned_service_id` from the equipment's current active assignment when left blank.
- **The Phase 3 public QR lookup page grows a report form**, rather than becoming a new
  surface: `GET /report/{token}` stays public/unauthenticated (unchanged, preserving Phase 3's
  shipped contract) — only the new `POST /report/{token}` (`AnomalyReportController`) requires
  a session (the app's existing `redirectGuestsTo` sends a guest straight to Filament login). A
  guest sees the same read-only card plus a "log in to report" link instead of the form.
- Legacy-matched duplicate-report guard: an asset with an already-open ticket shows "already
  reported" instead of a second form (`MaintenanceTicket::hasActiveTicketFor()`).
- New `anomaly-report` Redis rate limiter (per-user + per-QR-token), mirroring the existing
  `qr-lookup` pattern — Security.md §5 flags this endpoint as the single most abuse-prone in
  the module.
- Queued `SendTicketNotification` to A3 + the routed service's `responsible_user` on creation
  (ids/labels only, Security.md §7).
- `MaintenanceTicketResource` (Filament): A3 full CRUD; N2 read-only + FacultyScope (via
  equipment/local → building, the same cascading branch pattern as `Assignment`); N3 read-only
  unscoped; Service technique read-only unscoped (no per-technician sub-scoping exists anywhere
  in the schema yet); Enseignant/tout_utilisateur Create-only (report, no browsing) — matches
  the role matrix's explicit "report anomalies" grant, nothing wider.
- 18 new Pest tests (147 total): QR-scan creation + SLA/reference correctness, duplicate-report
  guard (both directions — blocked while active, allowed again once terminal), rate limiting,
  guest rejection, service-routing + notification, and RBAC across all six roles.

### Fixed (user-caught, flagged not guessed)
- `Schema.md`/`Phases.md` describe ticket priority as "defaulted per category rules" but never
  specify the mapping. Direct inspection of the legacy report-submission component
  (`ReportView.tsx`) shows it was never actually implemented there either — every QR-scan
  report is hardcoded urgent/48h, with no category picker on the form at all. Matched the
  legacy exactly instead of inventing a mapping; flagged in `PROGRESS.md` as an open question
  in case the university actually wants graduated severity by category.
- Ticket visibility for N2/N3/Service technique isn't spelled out explicitly in Security.md's
  role matrix either — extended the same read-only + FacultyScope convention already used by
  every other resource rather than leaving the new resource ungoverned (flagged in
  `PROGRESS.md`, not silently assumed).

## Phase 5 addendum follow-up — Timetable design fidelity (2026-07-06)

### Fixed (user-caught)
- The first `TimetableBuilder` pass ported the legacy grid's *mechanics* (6 time slots ×
  Sat–Thu columns) but not its actual *layout*. User asked directly whether the design still
  matched the legacy `ReservationsView.tsx` — it didn't, on three counts, now closed:
  - **3-column layout**: added the left "Academic Structure" sidebar the legacy app has —
    a read-only Faculty → Department tree (click a department to select it, replacing the
    plain `<select>`), scoped by the existing `FacultyScope` so N2 only ever sees their own
    faculty. Deliberately **not** reproduced: the legacy sidebar also inline-manages
    specialities/class-groups/levels, which aren't R13 entities (out of scope, flagged as a
    `TODO(confirm)` in `PROGRESS.md` rather than silently invented).
  - **Card anatomy**: each grid card now shows a status badge, a Heroicon `user-group` line
    (`level · student_group` — `student_group` is now collected on the grid's own form; the
    column already existed on `room_reservations` but wasn't wired to this page), a
    Heroicon `user` line (teacher), and a Heroicon `map-pin` line (room code · building
    name), matching the legacy card's icon rows instead of three bare lines of text.
  - **Status legend footer**: added, but intentionally shows only **Confirmed** — the
    legend's other two legacy states (Pending/Changed) can never appear here, since
    `TimetableBuilder`'s query only ever returns confirmed rows by construction (ad-hoc
    pending requests live on the separate `RequestReservation` page). Copying the legacy's
    3-dot legend verbatim would have shipped two permanently-dead states.
- `Faculty` was missing a `departments()` relation (needed for the new sidebar's
  Faculty→Department grouping) — added alongside the existing `services()`/`users()`
  relations.
- Caught while verifying visually: new Tailwind utility classes referenced in the blade file
  didn't render until `npm run build` re-ran — the compiled `public/build` asset bundle was
  stale from before this change. Not a code bug, but a build-step trap worth remembering for
  any future blade-only visual change in this stack.
- 3 new assertions added to the existing grid-placement Pest test, asserting the sidebar,
  department name, and legend text actually render (was previously only asserting the card's
  module name).

## Phase 5 addendum — Departments, academic terms, visual timetable grid (2026-07-06)

### Fixed (user-caught, before it shipped further)
- **Eager-loaded room picker**: `RequestReservation`'s `local_id` Select used `->options()`
  with a full `->get()` — fine at demo scale (70 rooms), a real problem at university scale
  (every room dumped into the DOM on every render). Switched to Filament's lazy
  `getSearchResultsUsing()`/`getOptionLabelUsing()` pair — a bounded, server-side query per
  keystroke, matching how every other searchable select in the app already behaves. Audited
  every other `Select`/`SelectFilter` in the codebase: all `->relationship()` selects already
  use `->searchable()` (Filament's built-in lazy AJAX search, not an eager dump), and no
  Filament table disables pagination — this was the one genuine offender.

### Changed (owner decision)
- **The timetable gained the structure it actually has**: the academic year splits into 2
  semesters; a faculty manages several departments and fills each department's timetable one
  semester at a time. `room_reservations.department` (free text) is promoted to
  `department_id` (new `departments` table, belongs to a faculty, FacultyScope'd like
  `buildings`/`locals` minus the shared/central branch — N2 sees only their own faculty's
  departments) and a new `academic_term_id` (new `academic_terms` table — academic year +
  semester 1/2 + date range, a university-wide referential like `faculties`, A3-managed,
  N2/N3 read). A `timetable` row's weekly recurrence now runs to its term's `end_date` — the
  previous arbitrary "repeat until" date picker (capped at a configurable month count) is
  gone entirely.
- **Visual timetable grid, not just a form**: `TimetableBuilder`, a new custom Filament page,
  ports the legacy app's actual weekly grid — confirmed from the legacy `data.ts` seed source
  to be 6 fixed daily periods (`08:00–09:30` … `16:30–17:45`) × **Sat–Thu** columns (this also
  corrects an earlier extraction note that suspected the old UI showed Mon–Sat; direct
  inspection of the component actually driving the grid shows it always matched the Algerian
  working-week Prisma enum). N2 (their own faculty's departments) / A3 pick a department +
  academic term and fill the grid directly, with a side "Add to timetable" panel; the plain
  `RoomReservationResource` create form still exists underneath (both now share a
  `TimetableSlotService` extracted from the old `handleRecordCreation` logic) but the grid is
  the primary, department/term-scoped entry point. Cancelling a card cancels the whole
  recurring series (bulk update by `recurring_group_id`), not just one week.

### Added
- `departments` and `academic_terms` tables/models/policies/PermissionSeeder grants;
  `DepartmentResource` (N2 own-faculty CRUD via a scoped-exists rule against forged faculty
  ids, A3 everywhere) and `AcademicTermResource` (A3-managed, auto-generates its `label` from
  academic year + semester when left blank).
- `TimetableSlotService::createSeries()` — the single source of truth for "generate a
  weekly-recurring slot bounded by an Academic Term's end date, pre-validated for conflicts,
  all-or-nothing" — used by both `CreateRoomReservation` and `TimetableBuilder`.
- 14 new Pest tests (129 total): Department RBAC (A3 any faculty, N2 own-faculty-only with
  server-side scope enforcement against forged ids, resource denial for other roles),
  AcademicTerm RBAC + label auto-generation, TimetableBuilder page gating, grid cell
  placement, weekly slot creation bounded by the term, whole-series cancellation.

### Notes
- Gotcha hit and fixed: Filament's `->relationship()` Select assumes the schema is bound to a
  real, saveable Eloquent record to resolve things — `TimetableBuilder`'s form (a
  data-collection panel, not a Resource CRUD page) has no such record and threw
  `hasAttribute() on null`. Fixed by using the same lazy-search pattern as the
  `RequestReservation` fix above, for both the room and teacher selects.
- Migration reversibility and scratch-DB fresh-seed verified; the live dev DB was only
  additively migrated.

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
