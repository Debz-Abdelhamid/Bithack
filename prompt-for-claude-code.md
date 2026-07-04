You are rebuilding a module of a university ERP for **Université Badji Mokhtar Annaba (UBMA)**,
called **PUI‑UBMA**. The module is **R13 — Patrimoine** (facilities & asset management:
buildings, rooms, equipment/QR tracking, assignments, maintenance tickets, room reservations,
regulatory inspections).

## Context
A previous version of this exact project exists in this workspace under **`Patrimo-BitHack`**,
built with Node.js + Express + Prisma + PostgreSQL (Next.js frontend). We are rebuilding it
**from zero** with:
- **Laravel** (latest stable) + **Filament** as the admin/app panel
- **PostgreSQL**, running in **Docker** (never installed on the host)
- Visual design must match `Patrimo-BitHack` as closely as reasonably possible

Five planning documents are provided in the project root — **read all five, in this order,
before writing a single line of code**:
1. `Claude.md` — project rules, tech stack, non‑negotiables, definition of done
2. `Schema.md` — full data model, relationships, SLA logic, open questions
3. `ui-design.md` — design extraction checklist and Filament theming approach
4. `Phases.md` — the phase‑by‑phase build order (do not skip ahead)
5. `Security.md` — the role/permission matrix, hardening and performance requirements

## What I need from you, in order

1. **Inspect `Patrimo-BitHack` first, read‑only.** Extract design tokens (colors, fonts,
   spacing), the page inventory, and the previous Prisma schema for cross‑reference. Fill in
   the `TODO` sections of `ui-design.md` with what you actually find — don't guess.
2. **Do not delete `Patrimo-BitHack` until step 1 is complete and I've confirmed it.** Then
   archive it (git branch/tag or `/legacy` folder) rather than a blind `rm -rf`, and scaffold
   the new Laravel + Filament app in its place.
3. **Set up Docker first**: Laravel app container, PostgreSQL, Redis — nothing runs on bare
   metal. Confirm `docker compose up` gives a working app before moving on.
4. **Implement Roles & Permissions using exactly the source document's actors, plus Enseignant.**
   Do not invent additional roles. The full set is: **Gestionnaire patrimoine (A3)**,
   **Responsable faculté (N2)**, **Rectorat (N3)**, **Service technique** (named in Étape 5 of
   the workflow), **Tout utilisateur** (generic authenticated staff/students), and
   **Enseignant (teacher)** — added because teachers need recurring course‑slot room bookings,
   a materially different flow from a one‑off staff booking. `Security.md` §3 has the full
   matrix and scope per role. Use `spatie/laravel-permission` + `filament-shield` so
   roles/permissions are data, never hardcoded `if ($user->role === '...')` checks — but the
   *set* of roles itself stays as listed above unless I ask you to add more.
5. **Reuse two specific UI pieces from `Patrimo-BitHack` as‑is, not redesigned:**
   - The **interactive campus map** (Cartographie campus) — same library, same interactions,
     same data shape as the old app. Identify what it's built with during the extraction pass
     in step 1 and port it faithfully.
   - The **maintenance ticket board as a drag‑and‑drop Kanban** (columns = ticket status: new →
     assigned → in_progress → resolved → closed), matching the old app's board exactly. Check
     its drag library/column config in the old repo. Dragging a card between columns must go
     through the same permission‑checked status‑transition logic as any other status change —
     never a raw unvalidated field update triggered by the drag event.
6. **Follow `Phases.md` exactly, one phase at a time.** After each phase, show me: what was
   built, how it maps to the Definition of Done, and the completed Security checklist for that
   phase from `Security.md` §12. Wait for my go‑ahead before starting the next phase unless I
   tell you to run ahead.
7. **Security and performance are not a final pass — they are a gate on every phase**, per
   `Claude.md` §7. In particular:
   - Every mutating endpoint validated via Form Requests, authorized via Policies.
   - Rate limiting/throttling (Redis‑backed) on every endpoint reachable by a generic user —
     especially the QR‑scan/anomaly‑report endpoint and the room‑reservation endpoint, since
     those are the two flows most likely to be hit by many concurrent users at once (start of
     semester rush, campus‑wide inventory scans). The application must degrade gracefully
     under load, not crash or fall over.
   - Use queues (Redis + Horizon) for anything that doesn't need to be synchronous.
   - No raw SQL, no unescaped user content, validated file uploads, audit logging on sensitive
     models (`spatie/laravel-activitylog`).
8. **Testing**: Pest feature tests for every phase, including at least one "this role should be
   denied" case per new resource. `pint` and `phpstan` (Larastan) must pass in CI before you
   consider a phase complete.
9. **Where the source document is ambiguous** (exact SLA business‑day calendar, the monetary
   threshold that triggers N3/PAdES approval, exactly who approves `maintenance_budgets`), do
   **not** invent an answer — flag it to me explicitly as `TODO(confirm)` and proceed with the
   most conservative/secure default in the meantime.

Start with step 1 (inspecting `Patrimo-BitHack` and filling in `ui-design.md`) and report back
before doing anything destructive.
