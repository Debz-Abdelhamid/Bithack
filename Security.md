# SECURITY.md — Security & performance requirements

Applies to **every** phase in `Phases.md`. Nothing merges without the relevant items below checked.

---

## 1. Threat model summary
- **Actors with legitimate but unequal access**: from an anonymous‑ish "tout utilisateur" scanning
  a QR code, up to Rectorat (N3) approving major‑works budgets. Privilege boundaries must be strict.
- **High‑burst public‑ish endpoints**: QR scan / anomaly reporting and room booking are the two
  flows most likely to be hit by many real users at once (start of semester, campus‑wide inventory
  campaigns) — these are both a security *and* an availability concern.
- **Sensitive artifacts**: PV de réception (PAdES‑signed legal documents), budget/financial data,
  personal data of staff/students (GDPR‑equivalent obligations under Algerian Law 18‑07 on
  personal data protection) — apply data‑minimization and access control accordingly.

---

## 2. Authentication
- Laravel Fortify (or Breeze scaffolding) underneath Filament's auth, **not** a hand‑rolled login.
- Password policy: minimum length + breached‑password check (`Password::defaults()` with
  `->uncompromised()`), no maximum‑length nonsense.
- **Mandatory 2FA** for: Gestionnaire Patrimoine (A3), Responsable Faculté (N2), Rectorat (N3).
  Optional but encouraged for Service technique and Enseignant.
- Session: `secure`, `http_only`, `same_site=lax` cookies; short idle timeout for elevated roles.
- Login attempts throttled per §5 below — this is both a security and an availability control.
- **Self-service registration** (added 2026‑07‑04 by project owner): open **only** to institutional
  email domains (exact-match allowlist, `TODO(confirm)` final domain list incl. student subdomains),
  mandatory email verification before any panel access, throttled per-minute (Filament built-in) +
  per-email + hourly per-IP cap, and the account receives exactly the `tout_utilisateur` role.
  Admin-provisioned accounts are created **pre-verified** — the administrator entering an
  institutional address is the trust step. Registration can be disabled without deploy
  (`PATRIMO_REGISTRATION_ENABLED`).

---

## 3. Authorization — role matrix (source doc only, + Enseignant)

The workflow document names four actor groups directly — **A3 (Gestionnaire patrimoine)**,
**N2 (Responsable faculté)**, **N3 (Rectorat)**, and **Service technique** (Étape 5: "A3 --
Service technique") — plus a generic **"Tout utilisateur"** for room booking and anomaly
reporting. Per your instruction, the role set is exactly that, plus **Enseignant**:

| Role | Source | Scope in R13 |
|---|---|---|
| Gestionnaire Patrimoine (A3) | explicit | full CRUD inventory, affectations, ticket oversight, closes tickets |
| Responsable Faculté (N2) | explicit | approve for their faculty: affectations, reservations, standard PV |
| Rectorat / Direction (N3) | explicit | approve grands travaux, high‑value budgets, N3‑threshold PV |
| Service technique | explicit (Étape 5) | plans and carries out interventions on tickets |
| Enseignant (teacher) | added at your request | **book rooms/labs (incl. recurring course slots)** — the only booking-initiator role; report anomalies |
| Tout utilisateur (staff/students, generic) | explicit | **read-only**: view timetables (emploi du temps) & room availability; report anomalies via QR scan. *(Changed 2026-07-04 by project owner: booking initiation removed — the source doc granted it, but UBMA policy reserves booking for teachers. N2 approval and A3 administration of reservations are unchanged.)* |

No student/technician/finance/compliance role split beyond this — implement as configurable
role/permission data (`filament-shield`) so it stays easy to adjust later if you decide you need
a finer split, but nothing beyond this table ships by default.

One non‑negotiable that isn't a "business" role from the workflow: Filament itself needs one
technical full‑access account to install/manage the panel and administer `filament-shield`
permissions (created in Phase 1). That's an operational necessity of the framework, not an
extra actor in your workflow diagrams.

### Enforcement rules
- Every Filament Resource ⇒ a Policy class ⇒ permissions checked via `->can()`, never `->hasRole('A3')`
  string comparisons scattered through the app.
- Query scoping (N2 sees only their faculty's data) via Eloquent **global scopes** driven by the
  authenticated user's `faculty_id`, not per‑query manual filtering that's easy to forget.
- **`users.faculty_id` semantics per role** *(clarified 2026‑07‑05 by project owner)*:
  - **N2**: authorization boundary — **required** (an N2 without a faculty has an empty scope);
    defines what they see/administer/approve.
  - **Enseignant / tout utilisateur**: organizational affiliation only — **never** restricts what
    rooms they can search or request; a teacher may request any bookable room campus‑wide.
  - **A3 / N3 / super admin / service technique**: left empty = central, university‑wide scope.
  - **Reservation approval routing (Phase 5) follows the ROOM's faculty** (via its building),
    not the requester's — a Sciences teacher booking a Technology amphi is approved by
    Technology's N2.
- Principle of least privilege by default: a new role starts with **zero** permissions, granted explicitly.

---

## 4. Input validation & injection protection
- Laravel Form Requests for every mutating endpoint; no inline `$request->all()` mass assignment.
- Eloquent/query builder everywhere — no raw string‑concatenated SQL.
- File uploads (equipment photos, ticket photos, PV/control certificates): validate MIME type by
  content (not just extension), enforce max size, store outside the public webroot when the
  document is sensitive (PV, budget attachments), serve via a signed, permission‑checked route.
- Sanitize/escape all user‑submitted free text rendered back in Filament tables/infolists (Blade
  escapes by default — never bypass with `{!! !!}` on user content).

---

## 5. Rate limiting & throttling (this is the "app must not fall over" requirement)
- Global API/web throttle middleware on every route group, tuned per endpoint sensitivity:
  - Login: strict per‑IP + per‑account throttle (e.g. Laravel's built‑in `throttle:login` pattern
    with lockout + exponential backoff).
  - QR‑scan / anomaly‑reporting endpoint: per‑user **and** per‑QR‑token throttle — this is the
    single most likely endpoint to be hammered (mass inventory campaigns, or abuse) and it must
    degrade gracefully, not create ticket floods or fall over.
  - Room reservation endpoint: per‑user throttle to prevent slot‑spamming/hoarding.
  - `/broadcasting/auth` (private‑channel authorization, Reverb/Pusher protocol): authenticated
    session required + per‑user throttle. **Private channels only** — no public or presence
    channels carry business events; a user may only subscribe to their own
    `App.Models.User.{id}` channel.
- Use Redis as the throttle store (not the default `array`/file driver) so limits hold correctly
  across multiple app containers/workers.
- Return proper `429` responses with `Retry-After`, don't let unthrottled retries pile up on the DB.

---

## 6. Performance & availability under concurrent load
Direct response to: *"the app must not crash just because a large number of users connect at once."*
- **Caching**: Redis for config/route/view cache in production, and for hot read paths (dashboard
  KPIs, room availability lookups) with short TTLs + cache invalidation on writes.
- **Queues**: anything not required synchronously (SLA escalation checks, PDF/PAdES generation,
  notification emails) goes through a queue (`redis` driver) with Horizon for visibility, so a
  request burst doesn't block on slow work.
- **Database**: proper indexing (see `Schema.md` §5), avoid N+1 via eager loading in every Filament
  Resource/table column, connection pooling (e.g. PgBouncer) if the deployment scales beyond a
  single app instance.
- **Horizontal scaling readiness**: stateless app containers (sessions/cache in Redis, not file/local),
  so more `app` containers can be added behind the load balancer without code changes.
- **Consider Laravel Octane** (Swoole/RoadRunner) for the highest‑traffic phases (start of semester)
  if plain PHP‑FPM throughput proves insufficient in Phase 13 load tests.
- **Load testing**: run k6 or Apache Bench/Locust against login, QR‑scan, and reservation endpoints
  as part of Phase 13, with documented pass/fail thresholds agreed with the university beforehand.

---

## 7. Data protection
- Encrypt sensitive columns at rest where appropriate (e.g. PAdES signature metadata, any personal
  ID numbers if ever stored) using Laravel's `encrypted` Eloquent cast.
- Automated, tested database backups (pg_dump on a schedule, stored off‑container) — a patrimoine
  registry (legal asset ownership record) losing data is a real institutional risk, not just an IT one.
- Respect Algerian Law 18‑07 (protection of personal data) for any staff/student personal data
  collected — data minimization, defined retention, and a named data controller/contact.
- **Realtime notification payloads are delivery pings, not data carriers.** Broadcast payloads
  are limited to notification id, type and a short pre‑localized label — never personal data,
  financial amounts, document contents or report free text. The Laravel `notifications` table is
  the source of truth; the client fetches full content over the authenticated app connection.
  App credentials (`REVERB_APP_*`) live in `.env`/secrets, never committed.
  *(Resolved 2026‑07‑04: self‑hosted Laravel Reverb chosen — no third‑party data path, so the
  former hosted‑Pusher Law 18‑07 concern is moot; payload minimization is retained as
  defense‑in‑depth and would make a future switch to hosted Pusher safe.)*

---

## 8. Audit logging & monitoring
- `spatie/laravel-activitylog` on all sensitive models: `equipments`, `assignments`,
  `maintenance_tickets`, `reception_reports`, `maintenance_budgets`, role/permission changes.
- Centralized error/log monitoring (e.g. Sentry or equivalent self‑hosted option) — especially to
  catch throttle‑limit hits and repeated authorization failures, which are early signs of abuse.

---

## 9. Docker & deployment security
- Non‑root user inside app containers.
- Secrets via `.env`/Docker secrets, never baked into images; `.env` never committed.
- Postgres and Redis **not** exposed on public ports — only reachable from the app network.
- HTTPS/TLS termination in front of the app; HSTS, `X-Frame-Options`, `X-Content-Type-Options`,
  and a sane CSP configured (Filament‑compatible CSP — test carefully, Livewire needs some allowances).
- Keep base images pinned and updated; scan images (e.g. `docker scout`/Trivy) in CI.

---

## 10. Dependency & supply chain
- `composer audit` and `npm audit` in CI, failing the build on high/critical findings.
- Dependabot (or equivalent) enabled on the repo.

---

## 11. Digital signature (PAdES) specifics
- Signing keys/certificates stored as secrets, never in the repo.
- Signature metadata (`reception_reports.pades_signature_meta`) must capture signer identity and
  timestamp sufficient to be legally defensible — confirm exact legal requirements with the
  university's legal/administrative office before Phase 8.

---

## 12. Per‑phase security checklist (copy into each PR)
- [ ] All new mutating routes covered by a Form Request
- [ ] All new Resources have a Policy wired to `filament-shield` permissions
- [ ] Rate limiting applied where the endpoint can be hit by "tout utilisateur" or is high‑burst
- [ ] No raw SQL, no unescaped user content rendered
- [ ] File uploads (if any) validated by content‑type and size, stored appropriately
- [ ] Sensitive actions logged via activity log
- [ ] `composer audit` / `npm audit` clean
- [ ] Tests include at least one "should be denied" authorization case
- [ ] New broadcast events (if any): private per‑user channels only, minimal payload (ids/labels,
      no sensitive content), queued, and covered by a channel‑authorization test (user A cannot
      subscribe to user B's channel)

---

## 13. Incident response basics
- Documented process to revoke a compromised account/role immediately (Filament Shield makes
  permission changes instant, no redeploy needed — verify this is actually true in practice).
- Backup restore procedure tested at least once before go‑live, not just documented.
