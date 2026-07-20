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

> **Phase status.** Complete: Phase 1 (schema, enums, models, seeders), Phase 2
> (auth, rapid tests, sampling events, the custody state machine, QR, custody
> scanning), Phase 3 (registration/receiving, blind coding, the lab workbench
> behind the blind wall, maker-checker verdicts, report PDFs, and admin essentials),
> Phase 4 (disputes, resampling of the reference part, and the retention/
> destruction lifecycle), Phase 5 (the server-rendered **admin panel** for the
> internal roles, plus an interim FSO web fallback), and Phase 6 (**public
> tracking**, **SMS notifications**, a **TAT analytics dashboard**, and
> deployment readiness). The custody state machine is **complete**. The web build
> is **v1.0.0-web**; the Flutter field app (replacing the FSO web fallback) is the
> remaining work.

## Public tracking

Unauthenticated, mobile-first, rate-limited, `noindex` pages under `/track`:

- `GET /track` — landing (by license, by event code; QR opens tracking directly)
- `GET /track/p/{qr_token}` — the URL printed on QR labels since Phase 2
- `GET /track/l/{license_no}` — a business's finalized events
- `GET /track/e/{event_code}` — one event (simplified stage timeline, verdict badge
  at report issue, "after retest" tag, dispute-window note, report photo)
- `GET /t/{event_code}` — short link used in SMS

All public output goes through `PublicEventResource` (an allow-list — the third
"wall", after the blind wall and blind-report gating), which never exposes seals,
blind codes, witnesses, staff identities, temperatures, GPS, SOP details, or
individual test **parameters** (verdict only). An FBO can file a resampling
application from an UNFIT public page (honeypot + PK-phone check + 3/day/IP limit);
it reuses `DisputeService::file()` with zero new rules and returns a `D-YYYY-NNNNNN`
reference.

## SMS notifications

Gateway-swappable: implement `App\Contracts\SmsGateway` and point `SMS_DRIVER` at it.
Ships with `log` (default), `null`, and a `sendpk` template. Sends are queued
(`SendSms`, 3 tries + backoff) and every attempt is written to `sms_logs`. Triggers:
report issued (FBO + FSO), retest report (FBO), dispute filed/accepted/rejected, and
a **batched** hourly supervisor summary of SOP violations. Templates in
`lang/en/sms.php` (Urdu stubbed).

## Analytics (TAT dashboard)

`/analytics` (ADMIN + VERIFYING_OFFICER, read-only): live pipeline, turnaround per
stage computed from `custody_events` vs catalog TAT, SOP summary, and volume with the
**UNFIT→FIT retest overturn rate** flagged. Server-rendered CSS bars, cached 10 min.
`php artisan analytics:tat --from= --to=` prints the same numbers.

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
# Build the panel assets once (or `npm run dev` while working on views)
npm install
npm run build

php artisan serve
```

This starts the PHP development server on <http://127.0.0.1:8000>.

- **Admin panel (Phase 5):** the site root `/` — a server-rendered Blade + Alpine +
  Tailwind panel for the internal roles, with an interim FSO web fallback under
  `/field/*`. Log in at `/login` with a seeded account (below); seeded accounts are
  forced to change their password on first login. See [docs/PANEL.md](docs/PANEL.md).
- **API:** served under `/api/v1` — see [docs/API.md](docs/API.md) for every endpoint
  and copy-paste walkthroughs.

For panel development, `npm run dev` runs the Vite dev server (hot reload), or
`composer dev` runs the PHP server, queue worker, log tailer, and Vite together.

### Deploying the panel to shared hosting

Shared hosting (Hostinger) has no Node. Build the assets **locally** and upload the
result:

```bash
npm run build          # writes public/build/
# then upload public/build/ alongside the app
```

`public/build/` is the compiled Vite bundle the Blade views reference via `@vite`.

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

Each role maps to a stage of the workflow: **FSO** collects (and **TRANSPORT** carries),
**REGISTRATION_OFFICER** receives and blind-codes, **LAB_ANALYST** tests behind the blind
wall, **VERIFYING_OFFICER** issues the verdict, **ADMIN** administers.

## Background jobs

Report PDFs are rendered in a queued job (`database` driver), so a worker must be
running for reports to be produced:

```bash
php artisan queue:work
```

Without a worker, a verified sample stays at `VERIFIED` and no PDF appears — this is
by design, and `reports:retry-failed` re-queues anything left behind.

## Artisan commands

| Command | Description |
|---------|-------------|
| `php artisan migrate:fresh --seed` | Rebuild the schema and seed reference data |
| `php artisan sampling:prune-drafts` | Flag draft sampling events left unfinalized > 24h (never deletes). Accepts `--hours=`. Scheduled daily at 01:00. |
| `php artisan queue:work` | Process queued jobs (report PDF generation) |
| `php artisan reports:retry-failed` | Re-queue report PDFs for verified samples whose report never generated. Accepts `--limit=`. |
| `php artisan retention:process` | Flag settled reference parts as destruction-eligible (FIT, or UNFIT past the dispute window with no open dispute). Never destroys anything. Scheduled daily at 01:30. |
| `php artisan sms:violation-summary` | Batched supervisor SMS of SOP violations in the last hour. Scheduled hourly. |
| `php artisan analytics:tat --from= --to= --section=` | Print turnaround, volume, and quality analytics. |

## Configurable settings

Runtime rules live in the `settings` table (seeded by `SettingsSeeder`) rather than in
code:

| Key | Default | Meaning |
|-----|---------|---------|
| `dispute_window_days` | `7` | Days an FBO has to dispute an UNFIT verdict (Phase 5) |
| `same_day_transfer_deadline` | `20:00` | Samples reaching registration later than this on the collection day are flagged `SAME_DAY_TRANSFER` |
| `cold_chain_min_c` / `cold_chain_max_c` | `0` / `8` | Acceptable perishable temperature range; outside it a scan is accepted but flagged `COLD_CHAIN_BREACH` |
| `supervisor_phone` | *(blank)* | Mobile for the batched hourly SOP-violation summary SMS; blank disables it |

Report branding (logo, authority names) is in [config/pfa.php](config/pfa.php) — drop
the official crest in and point `PFA_REPORT_LOGO` at it.

## Notes for maintainers

- **The blind wall is a hard requirement, not a preference.** Lab analysts must never
  learn whose sample they are testing. Anything analyst-facing goes through
  `App\Http\Resources\BlindSamplePartResource`, and
  `tests/Feature/BlindWallTest.php` recursively scans every analyst payload for
  identifying keys *and* values. If that test fails, fix the leak — never the test.
- Premises lookups auto-create a `MANUAL` fallback record when a license number is
  unknown locally — a **temporary** measure until the PFA ~400k business database is
  integrated (`App\Services\PremisesResolver`).
- SOP deviations (late transfer, cold-chain breach) are **recorded, not blocking** —
  the sample still moves so lab work is not lost, and an admin resolves the flag via
  `/admin/sop-violations`. A *missing* perishable temperature is still a hard block.
- **Disputes never overwrite the original result.** An accepted dispute re-blinds and
  retests the reference part; the retest result is stored alongside the original, and
  the event detail exposes both.
- ⚠️ **Legal-precedence rule to confirm before production.** `final_verdict` currently
  takes the **retest** verdict over the original when a retest exists
  (`EventDetailResource`, `final_verdict_source`). This is a sensible default but the
  actual legal precedence under the Punjab Food Authority Act **must be confirmed with
  PFA legal** — it may differ (e.g. the more severe verdict prevails).
- Under PHP 8.5 the framework's bundled base config references a PDO constant that is
  deprecated in 8.5; it is harmless and does not occur on the 8.2/8.3 production
  target. `bootstrap/app.php` strips only `E_DEPRECATED` before config loads so it
  never leaks into responses.
