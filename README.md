# Patrimo — PUI-UBMA · R13 Patrimoine

Facilities & asset management module (buildings, rooms, QR-tracked equipment, assignments,
maintenance tickets, room reservations, regulatory inspections) for **Université Badji
Mokhtar Annaba**, built with **Laravel 12 + Filament 4 + PostgreSQL + Redis**, fully dockerized.

> Read `Claude.md`, `Schema.md`, `ui-design.md`, `Phases.md`, `Security.md` (in that order)
> before touching any code. They are the single source of truth for this repository.

## Quickstart (Docker only — nothing installs on the host)

```bash
cp .env.example .env          # then set DB_PASSWORD
docker compose build
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build
```

The admin panel is served at **http://localhost:8080/admin**.
Local demo accounts (all password `password`): `admin@demo.ubma.dz` (super admin),
`a3@`, `n2@`, `n3@`, `technique@`, `enseignant@`, `utilisateur@` … `demo.ubma.dz`.
Elevated roles (admin/a3/n2/n3) are forced to set up TOTP MFA on first login.
Realtime notifications run over the `reverb` container (ws://localhost:8081) — no account needed.
Self-registration is open only to `@univ-annaba.dz` addresses (configurable via
`PATRIMO_REGISTRATION_DOMAINS`), assigns the `tout_utilisateur` role and requires email
verification — all outgoing mail lands in **Mailpit: http://localhost:8025**.
Postgres and Redis are intentionally **not** published on host ports (Security.md §9);
reach them via `docker compose exec postgres psql …` / `docker compose exec redis redis-cli`.

### GUI database clients (Beekeeper, DataGrip…)

To connect a desktop client, create a git-ignored `docker-compose.override.yml` that
publishes Postgres on loopback only, then `docker compose up -d postgres`:

```yaml
services:
  postgres:
    ports:
      - "127.0.0.1:5432:5432"
```

Connect with host `localhost`, port `5432`, and the `DB_*` credentials from your `.env`.

### Do not run the app on the host

Never `php artisan serve` (or host PHP at all): the stack only works inside Docker — host PHP
lacks the `phpredis` extension and cannot resolve the `redis`/`postgres` service hostnames, so
you'll get "Class Redis not found" or connection errors by design. Use http://localhost:8080.

### vendor/ and node_modules/ live inside Docker volumes

For performance on Windows (bind-mount latency + OneDrive/antivirus scanning), the container
reads `vendor/` and `node_modules/` from named Linux volumes, not from your project folder.
Always run `composer …` and `npm …` **inside the container** (`docker compose exec app …`).
A `vendor/` folder on the host is only a convenience copy for IDE autocompletion and is not
what the app executes.

### Backups & restore

The `backup` container dumps the database daily into `storage/backups/` (kept 7 daily /
4 weekly / 6 monthly, git-ignored). Trigger one manually:

```bash
docker compose exec backup /backup.sh
```

Restore into the running database (destructive — restores over current data):

```bash
docker compose exec -T postgres psql -U patrimo -d patrimo -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker compose exec -T postgres sh -c "zcat /var/lib/postgresql/data/../../../backups/daily/<file>.sql.gz | psql -U patrimo -d patrimo"
# or from the host: gunzip -c storage/backups/daily/<file>.sql.gz | docker compose exec -T postgres psql -U patrimo -d patrimo
```

### After changing `.env`

The `queue` and `reverb` containers are long-running PHP processes — they keep the environment
they booted with. After any `.env` change, run `docker compose restart queue reverb`
(web requests through `app` pick up `.env` live in local dev; in production, also run
`php artisan config:cache && php artisan queue:restart`).

## Quality gates (run before every commit)

```bash
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app ./vendor/bin/pest
```

CI (`.github/workflows/ci.yml`) enforces the same three plus `composer audit` and
`npm audit --audit-level=high`.

## Repository layout notes

- `legacy/Patrimo-BitHack/` — the retired Node/Express/Prisma + Next.js version, archived
  with its own git history for design reference. Ignored by this repo's git. Do not delete;
  design tokens extracted from it live in `ui-design.md`.
- `docker/` — php-fpm image (non-root) and nginx config.
- Build phases and their Definition of Done: `Phases.md`. Per-phase security checklist: `Security.md` §12.
