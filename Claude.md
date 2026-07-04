# CLAUDE.md — PUI‑UBMA · Module R13 "Patrimoine"
> Master instruction file for any AI agent (Claude Code) working on this repository.
> Read this file **first**, on every session, before touching any code.

---

## 1. Project context

**PUI‑UBMA** = *Progiciel Unifié Intégré — Université Badji Mokhtar Annaba*.
It is a full university ERP made of ~20+ business modules called **Rubriques** (R1…R23+).
This repository currently implements **one rubrique**: **R13 — Patrimoine**
(inventory & facilities management: buildings, rooms, equipment, QR‑tracked assets,
assignments, maintenance tickets, room bookings, and regulatory inspections).

The project was previously built by the team with **Node.js + Express + Prisma + PostgreSQL**
(frontend in Next.js, project folder **`Patrimo-BitHack`**). That version is being **retired**.
We are **rebuilding from zero** on:

- **Backend/Admin:** Laravel (latest LTS‑compatible version) + **Filament** (panel builder)
- **Database:** PostgreSQL, running in **Docker**
- **Design:** must visually match the old `Patrimo-BitHack` Next.js app (see `ui-design.md`)

This file, together with `Schema.md`, `ui-design.md`, `Phases.md` and `Security.md`,
is the **single source of truth** for how this project must be built. If any instruction
here conflicts with a general Laravel/Filament "best practice" you know, prefer the more
**secure** and more **explicit** option, and flag the conflict instead of silently picking one.

---

## 2. Before writing any code — mandatory session checklist

1. Read `Claude.md` (this file), `Schema.md`, `ui-design.md`, `Phases.md`, `Security.md` in that order.
2. If a local copy of the old `Patrimo-BitHack` project is present in the workspace,
   **inspect it read‑only first** (do not delete anything yet):
   - `tailwind.config.*`, `globals.css` / theme files → design tokens
   - `prisma/schema.prisma` → previous data model, to cross‑check against `Schema.md`
   - `/app` or `/pages` routes → page inventory, to fill the mapping table in `ui-design.md`
3. Confirm which **Phase** (see `Phases.md`) is currently active. Never start a phase whose
   predecessor hasn't passed its "Definition of Done" and its Security/Performance gate.
4. Only **after** the design tokens and page inventory have been extracted and written into
   `ui-design.md`, archive the old project (`git mv` into `/legacy` or a separate branch/tag —
   **do not `rm -rf` until the user has explicitly confirmed the archive step is done**) and
   scaffold the new Laravel app in its place.
5. State your plan for the current phase in 5–10 bullet points before generating files.

---

## 3. Tech stack (locked decisions)

| Concern | Choice | Notes |
|---|---|---|
| Framework | Laravel (current stable) | Use Laravel Sail or a custom `docker-compose.yml` — see §5 |
| Admin/App UI | Filament v4 (or latest stable v3) | Panel builder for 100% of CRUD screens |
| Database | PostgreSQL 16+ | Must run in Docker, never installed on host |
| Cache / Queues / Rate limiting store | Redis | Required for throttling, queues, session store at scale |
| Auth | Laravel Fortify/Breeze conventions wired into Filament's auth, + 2FA | See `Security.md` |
| Roles & Permissions | `spatie/laravel-permission` + `bezhansalleh/filament-shield` | See §4 and `Security.md` §3 |
| Background jobs | Laravel Queues (`database` or `redis` driver) + Horizon in prod | SLA escalation, notifications, PDF/PAdES signing |
| Realtime notifications | Laravel Broadcasting over **Laravel Reverb** (self‑hosted in our Docker stack, speaks the Pusher protocol; client = Laravel Echo + `pusher-js`) | **Notifications only** — no live-collaboration/live-board features. Private per‑user channels, broadcast events queued via Redis, minimal payloads (rules in `Security.md` §5/§7). Bootstrap lands in Phase 1; each later phase only adds its own notification events. *Decision 2026‑07‑04: Reverb chosen over hosted Pusher (cost, Law 18‑07 data locality, no connection caps). Because the protocol/client are identical, switching to hosted Pusher later is an `.env` change.* |
| PDF / digital signature (PAdES) | dedicated package (e.g. `fpdi`/`tcpdf` + a PAdES‑capable signing lib) | Used for PV de réception (see `Schema.md`) |
| QR codes | `simplesoftwareio/simple-qrcode` (or `endroid/qr-code` directly) | One QR per equipment, encodes a signed/opaque asset URL, not raw DB id |
| Testing | Pest (preferred) or PHPUnit | Every phase ships with tests, see `Phases.md` |
| Static analysis | Larastan (PHPStan level ≥ 5), Laravel Pint | Run in CI before merge |
| i18n | **English (primary UI language — project‑owner decision 2026‑07‑05, reversing the earlier French‑first plan)**; all strings still go through `lang/` files (`lang/en` primary, `lang/fr` maintained for a future French pass, Arabic reserved for RTL) | See `ui-design.md` §7 |
| Timezone | `APP_TIMEZONE=Africa/Algiers` app‑wide (storage and display) | Set 2026‑07‑05, before time‑arithmetic features (reservations Phase 5, SLA Phase 7). Business‑day calendar remains `TODO(confirm)` (`Schema.md` §6) |

---

## 4. Roles & Permissions — do not hardcode, use the matrix

The role set is deliberately kept to what the source document names — **A3 (Gestionnaire
patrimoine)**, **N2 (Responsable faculté)**, **N3 (Rectorat)**, **Service technique** (Étape 5),
and generic **"Tout utilisateur"** — plus **Enseignant**, added on request. See `Security.md` §3
for the full matrix. Do not introduce additional roles beyond this table without being asked.
Implement roles as **data**, not as code branches:

- Use `spatie/laravel-permission` teams/roles/permissions tables — never `if ($user->role === 'A3')`.
- Every Filament Resource must define a `Policy` class; policies read permissions, they never
  hardcode role names either — check `->can('...')`, not `->hasRole('A3')`, inside business logic.
- Install `filament-shield` to auto‑generate resource permissions and manage them from a UI,
  so permissions can be adjusted by an admin without a code deploy.

---

## 5. How to run the project (Docker)

- The **only** supported way to run PostgreSQL (and Redis) locally or in CI is Docker.
- Provide a `docker-compose.yml` at repo root with at least: `app` (php-fpm or Octane),
  `postgres`, `redis`, `nginx` (or use Laravel Sail's default topology if simpler).
- Secrets (DB password, `APP_KEY`, mail credentials, signing keys) come from `.env`, which is
  **never committed**. Ship a `.env.example` with safe placeholders.
- No developer should need anything installed on the host beyond Docker + Docker Compose.

---

## 6. Coding conventions

- PSR‑12, enforced by Laravel Pint (`pint --test` in CI).
- Filament Resources organized by domain folder: `app/Filament/Resources/Patrimoine/...`
  (leaves room for future rubriques to live in their own namespaces later).
- Form Requests for all validation; never validate inline in a controller/Livewire action beyond
  trivial cases.
- Eloquent only — no raw SQL string concatenation. Use query builder bindings for anything dynamic.
- Every migration is reversible (`down()` implemented).
- Every table, column and enum name is **English, snake_case** (see `Schema.md` §0 for the
  rationale) — UI labels are French, sourced from Filament `->label()` calls or `resources/lang/fr`.
- No secrets, no real university data, no personal data in seeders beyond obviously fake demo data.

---

## 7. Non‑negotiables (apply to every single phase)

1. **Security gate**: nothing merges without the relevant checklist items in `Security.md` §12 ticked.
2. **Performance gate**: any endpoint that can be hit by "tout utilisateur" (room booking, QR scan,
   anomaly reporting) must be behind rate limiting/throttling (`Security.md` §5) — the app must
   **not fall over** under a burst of concurrent users (login rush at start of semester, mass QR
   scanning during an inventory campaign, etc.).
3. **RBAC gate**: no new screen ships without a Policy + Filament Shield permission wired in.
4. **i18n gate**: no hardcoded French/English string in Blade/Filament — always through translation files.
5. Every phase ends with automated tests (feature + policy tests at minimum) and a short
   `CHANGELOG` entry.

---

## 8. Definition of Done (per phase, generic template)

A phase is "Done" only when **all** of the following are true:
- [ ] Migrations + factories + seeders exist and `php artisan migrate:fresh --seed` works in Docker
- [ ] Filament Resources (list/create/edit/view) exist for every entity introduced in the phase
- [ ] Policies restrict access exactly per the role matrix (`Security.md` §3)
- [ ] Feature tests cover the happy path + at least one authorization‑denied path
- [ ] Rate limiting applied to any public/mutating route introduced
- [ ] `pint` and `phpstan` pass with zero errors
- [ ] The relevant section of `Schema.md` / `ui-design.md` is updated if reality diverged from plan

---

## 9. Communication style for this repo

- Commit messages: Conventional Commits (`feat:`, `fix:`, `chore:`, `sec:`…).
- When you (the agent) are uncertain about a business rule (e.g., exact SLA escalation policy,
  exact budget‑approval threshold for N3 vs N2), **do not silently invent it** — write a `TODO(confirm):`
  comment and note it in your response to the user instead of guessing at university policy.
