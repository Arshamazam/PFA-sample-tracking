# PFA Sample Testing & Tracking System

A chain-of-custody system for food sample testing under the Punjab Food Authority
Act 2011. It digitises the legal sampling SOP end to end: a field officer records a
rapid screening test, collects a formal sample that is split into three sealed
parts in front of a witness (the "Rule of Three"), and every subsequent movement of
each part is captured as an immutable, timestamped custody event. Each part carries
its own QR code and tamper seal so it can be tracked from the point of collection
through the laboratory to final disposal.

The system is built for the realities of the deployment: samples are analysed
"blind" so lab staff never see the business identity, perishable samples enforce a
cold-chain temperature record at each hop, and disputed results can trigger a retest
of the reserved reference part. It is designed to run on modest shared hosting with
a MariaDB/MySQL database and no long-running processes, and exposes a token-based
JSON API intended for a field mobile app.

> **Phase status.** Phase 1 (schema, enums, models, seeders) and Phase 2 (auth,
> rapid tests, sampling events, the custody state machine, QR, and custody scanning)
> are complete. Registration/blind coding, the lab workbench, verdicts, disputes,
> and public tracking are later phases.

## Documentation

- [docs/SCHEMA.md](docs/SCHEMA.md) — database schema, enums, and the part lifecycle.
- [docs/API.md](docs/API.md) — every Phase 2 endpoint with request/response examples
  and a copy-paste happy-path walkthrough.

## Requirements

- PHP 8.2+ (developed on 8.5; production target 8.2/8.3)
- Composer
- MariaDB or MySQL

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# edit .env DB_DATABASE / DB_USERNAME / DB_PASSWORD as needed

# 3. Create the database, then run migrations + seeders
php artisan migrate:fresh --seed
```

## Running the project

```bash
php artisan serve
```

This starts the PHP development server on <http://127.0.0.1:8000>. The API is served
under `/api/v1` — see [docs/API.md](docs/API.md) for every endpoint and a copy-paste
happy-path walkthrough, and authenticate with one of the seeded test accounts below.

The API phases have no frontend to build, but if you are working on the Blade views,
`composer dev` runs the server, queue worker, log tailer, and Vite together.

### Changing the port

`php artisan serve` listens on port **8000** by default. To override it for a single
run:

```bash
php artisan serve --port=8080
```

To change it permanently, set `SERVER_PORT` in `.env`. Artisan reads it as the default
for `--port`, so it applies to `composer dev` too:

```dotenv
SERVER_PORT=8080
```

`SERVER_HOST` works the same way for the bind address. Binding to `0.0.0.0` makes the
API reachable from other devices on the network, which is what you want when testing
against the field mobile app on a real phone:

```dotenv
SERVER_HOST=0.0.0.0
SERVER_PORT=8080
```

**When you change the port, update `APP_URL` to match:**

```dotenv
APP_URL=http://127.0.0.1:8080
```

`App\Services\QrService` bakes `APP_URL` into every part's QR code as
`{APP_URL}/track/p/{qr_token}`. If it is stale, the QR codes printed for sample parts
point at the wrong port and scanned tracking links will not resolve — and because the
code is fixed at generation time, fixing `APP_URL` later does not repair codes that
were already issued.

Two things worth knowing:

- If the port is busy, `artisan serve` retries on the next port up (8001, 8002, … up
  to `--tries`, default 10). This only happens while the port is left at its default —
  passing `--port` or setting `SERVER_PORT` pins it, and a busy port fails instead of
  moving. Pinning is usually what you want, since a server that silently moves ends up
  disagreeing with `APP_URL`.
- `DB_PORT` in `.env` is the MariaDB/MySQL port (3306), not the application's. Changing
  it will not move the web server.

### Tests

Feature/unit tests run against a dedicated schema (`pfa_sample_tracking_test`):

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS pfa_sample_tracking_test"
php artisan test
```

## Seeded test accounts

`RoleUsersSeeder` creates one account per role. **These are TEMPORARY development
accounts** pending integration with the PFA staff database (the same interim pattern
as the PFA Warehouse system) — do not ship them to production.

| Role | Email | Password |
|------|-------|----------|
| Food Safety Officer | `fso@pfa.test` | `password` |
| Transport Officer | `transport@pfa.test` | `password` |
| Registration Officer | `registration_officer@pfa.test` | `password` |
| Lab Analyst | `lab_analyst@pfa.test` | `password` |
| Verifying Officer | `verifying_officer@pfa.test` | `password` |
| Administrator | `admin@pfa.test` | `password` |

The field APIs in this phase are exercised by the **FSO** (and **TRANSPORT** for
custody scanning).

## Artisan commands

| Command | Description |
|---------|-------------|
| `php artisan migrate:fresh --seed` | Rebuild the schema and seed reference data |
| `php artisan sampling:prune-drafts` | Flag draft sampling events left unfinalized > 24h (never deletes). Accepts `--hours=`. Scheduled daily at 01:00. |

## Notes for maintainers

- Premises lookups auto-create a `MANUAL` fallback record when a license number is
  unknown locally — a **temporary** measure until the PFA ~400k business database is
  integrated (`App\Services\PremisesResolver`).
- Under PHP 8.5 the framework's bundled base config references a PDO constant that is
  deprecated in 8.5; it is harmless and does not occur on the 8.2/8.3 production
  target. `bootstrap/app.php` strips only `E_DEPRECATED` before config loads so it
  never leaks into responses.
