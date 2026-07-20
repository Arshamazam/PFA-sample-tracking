# Deployment Runbook — Hostinger shared hosting

Target: PHP 8.2 / 8.3 + MariaDB, no shell daemons, symlinks possibly blocked.

## 0. Pre-flight (local)

```bash
php -v                       # confirm 8.2/8.3 target locally too
composer install --no-dev --optimize-autoloader
npm ci && npm run build      # produces public/build/ (shared hosting has no Node)
php artisan test             # must be green
php artisan config:cache && php artisan route:cache   # must succeed, then clear
php artisan config:clear && php artisan route:clear
```

## 1. Document root

Point the domain/subdomain document root at **`/public`** (Hostinger: Website →
domain → "document root"). Never expose the app root. `storage/` and `.env` must
stay outside the web root (they already are under the app root, not `public/`).

## 2. Upload

Upload the whole app **including `public/build/`** (the compiled Vite bundle — it is
git-ignored, so it won't be in a `git clone`; upload it from your local build).
Exclude `node_modules/`, `.git/`, and your local `.env`.

## 3. `.env` (production)

Copy `.env.production.example` to `.env`, fill secrets, then:

```bash
php artisan key:generate      # if APP_KEY is empty
```

Key production values:

```dotenv
APP_ENV=production
APP_DEBUG=false               # never true in production (leaks stack traces)
APP_URL=https://track.pfa.example
FORCE_HTTPS=true              # redirect + HSTS (App\Http\Middleware\ForceHttps)

SESSION_SECURE_COOKIE=true    # cookies only over HTTPS
SESSION_DRIVER=database

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
CACHE_STORE=file

CORS_ALLOWED_ORIGINS=https://track.pfa.example,https://panel.pfa.example

SMS_DRIVER=log                # switch to the PFA gateway driver once provided
# SENDPK_ENDPOINT=... SENDPK_API_KEY=... SENDPK_SENDER=PFA
```

## 4. Storage — no `storage:link` required

Every uploaded file is written to the **private** `local` disk
(`storage/app/private/…`) and served only through:
- `GET /api/v1/files/{path}` — authenticated, path-traversal-guarded, and
- `GET /track/report-photo/{part}` — public, but serves **only** the
  `report_photo_path` of a `REPORT_ISSUED` part, never an arbitrary path.

Nothing uses the `public` disk or `asset('storage/…')`, so a blocked `storage:link`
symlink does not matter. If you later add public assets, run `php artisan
storage:link`; if symlinks are blocked, route them through the file controller instead.

## 5. First-deploy commands

```bash
php artisan migrate --force          # --force is required in production
php artisan db:seed --force          # ONLY on a fresh install (seeds test accounts —
                                     # then immediately rotate/disable them)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Seeded accounts have `must_change_password = true`; still, disable or delete the
`*@pfa.test` accounts before go-live.

## 6. Cron (Hostinger → Advanced → Cron Jobs)

Shared hosting has no long-running worker, so run the queue in short bursts and the
scheduler every minute:

```cron
* * * * * cd /home/USER/app && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
* * * * * cd /home/USER/app && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler drives: `sampling:prune-drafts` (daily), `retention:process` (daily),
`sms:violation-summary` (hourly). Report PDFs and SMS are queued jobs, handled by the
`queue:work` line above.

## 7. Deploying an update

```bash
php artisan down
# upload changed files (+ public/build/ if assets changed)
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 8. Rollback

- **Code:** re-upload the previous release (keep the last known-good copy, or
  `git checkout <prev-tag>` then rebuild assets locally and re-upload).
- **DB:** each schema migration ships a `down()`; `php artisan migrate:rollback
  --force` reverses the last batch. Take a DB dump before every migrate.
- After rollback: `php artisan config:cache && route:cache && view:cache`.

## Security sweep (state at v1.0.0-web)

- **APP_DEBUG** — defaults to `false`; production template sets it explicitly.
- **HTTPS** — `FORCE_HTTPS=true` redirects http→https (301) and adds HSTS; env-gated
  so local dev is unaffected.
- **Cookies** — `SESSION_SECURE_COOKIE=true`, `http_only` and `same_site=lax` are
  Laravel defaults.
- **CORS** — `config/cors.php` locked to `CORS_ALLOWED_ORIGINS`; credentials off
  (the app uses bearer tokens, not cookies, cross-origin).
- **Rate limits** — login 5/min/IP, whole API 60/min (user or IP), public tracking
  30/min/IP, public dispute filing 3/day/IP.
- **File uploads** — every one of the 14 upload endpoints validates
  `file, image, max:5120` (5 MB). The `image` rule excludes SVG (XSS vector) and
  validates real image content, mitigating mime spoofing.
- **Unauthenticated file access** — only `GET /track/report-photo/{part}`, and it
  serves solely the report photo of a `REPORT_ISSUED` part. All other files require
  auth via the file controller.
- **Public data** — every `/track` response goes through `PublicEventResource` (the
  public wall, allow-list) and is `noindex`.
- **config:cache + route:cache** — both succeed (no closure routes).
