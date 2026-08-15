# Deployment Runbook — Hostinger

| Field | Value |
|---|---|
| **Purpose** | Get the app from a git push to actually running on Hostinger. Translates Implementation_plan.md's Vercel-oriented S1.2–S1.3 to this project's single-host architecture (see CLAUDE.md "Hosting"). |
| **Status** | **Live.** Deployed and verified 2026-08-15 at `https://visa.geninnovations.net`. |
| **Target** | Hostinger Cloud Startup shared hosting, SSH access (hPanel → Advanced → SSH Access), PHP 8.3 (already the account default — no MultiPHP change needed) |

---

## 1. Server layout — read this before touching anything

Two directories matter, and they are **not** the same thing:

| Path | What it is |
|---|---|
| `/home/u508116592/visa_platform_app` | The real application — full git checkout, `vendor/`, `.env`, everything. **Not web-accessible.** All `artisan`/`composer` work happens here. |
| `/home/u508116592/domains/geninnovations.net/public_html/visa` | The **web root** for `visa.geninnovations.net`. Contains only: `index.php` (a modified copy — see §2), `.htaccess`, `favicon.ico`, `robots.txt`, and a copy of `public/build/`. |

### Why it's split this way, not the usual "point document root at `public/`"

The originally-planned approach — set the subdomain's document root directly to `visa_platform_app/public` — **does not work on this Hostinger plan**. Its subdomain UI (Websites → Domains → Subdomains) has a "Custom folder for subdomain" checkbox at creation time, but in practice the resulting directory stayed pinned to the platform's own fixed convention (`domains/<domain>/public_html/<subdomain>`) regardless of what was entered. No document-root override was ever accepted for a path outside that structure — confirmed by testing, not assumed.

The fix used instead is the standard "shared hosting Laravel" pattern: the fixed web root gets a **hand-written `index.php`** that bootstraps Laravel from its real location using an absolute path, rather than the relative `__DIR__.'/../...'` paths the default `public/index.php` uses. `bootstrap/app.php` still resolves its own base path correctly when required this way, because PHP's `__DIR__` always refers to the *including* file's own location, not the includer's — so nothing inside Laravel needs to know it's being loaded from an unusual place.

This still fully satisfies BR-09/FR-DM-01 (no application code, `.env`, or config is web-exposed) — arguably more robustly than a document-root override would have, since the web root physically contains nothing but static assets and the one bootstrap file.

## 2. The web-root `index.php` (already deployed, shown here for reference)

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appPath = '/home/u508116592/visa_platform_app';

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

If `visa_platform_app` is ever moved or renamed, this is the one file that needs its `$appPath` updated.

## 3. Cron jobs (hPanel → Advanced → Cron Jobs) — configured and verified

No `crontab` access over SSH on this host — hPanel-managed only, via a form (PHP mode) rather than raw crontab
syntax. The command field only accepts the part *after* a fixed prefix (`/usr/bin/php /home/u508116592/` on
this account), and — worth knowing, since it wasn't obvious the first time — **the Minute/Hour/Day/Month/
Weekday selectors do not default to "every"/`*` on their own; each one must be explicitly set**, or use the
"Common Settings" dropdown's "Every Minute" preset if present. Two jobs, both every minute:

| | Command (after the fixed prefix) |
|---|---|
| Scheduler | `visa_platform_app/artisan schedule:run` |
| Queue worker | `visa_platform_app/artisan queue:work --stop-when-empty --max-time=50` |

`--max-time=50` keeps the queue worker from overlapping into the next minute's cron tick.

**Verified 2026-08-15**: dispatched a job from a real file (see the closure-serialization note in §8), confirmed
it sat unprocessed for several minutes while the cron config was wrong (both jobs had accidentally saved as
`schedule:run` with non-`*` schedules — one hourly, one restricted to Monday/January/1am), then re-verified
clean after fixing it: job processed within 15 seconds of the next minute tick, fully unattended.

## 4. Node/npm on the server — deliberately not relied on

Shared hosting frequently has no Node.js runtime. Frontend assets are built **locally** (or later, in CI — Stage 1 S1.5) and the compiled `public/build/` output is copied up separately — `npm`/`vite` never run on Hostinger itself.

## 5. Deployment steps

```bash
# Local machine — build assets first
npm run build

# SSH to Hostinger
ssh -i ~/.ssh/visa_platform_hostinger -p 65002 u508116592@157.173.223.128

# On the server, every deploy:
cd ~/visa_platform_app
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart          # in case a queued job references code that just changed
```

Then, from the local machine, sync the freshly-built assets to **both** the app directory and the live web root (the web root's copy is what's actually served):

```bash
rsync -avz --delete -e "ssh -i ~/.ssh/visa_platform_hostinger -p 65002" \
  public/build/ u508116592@157.173.223.128:~/visa_platform_app/public/build/

rsync -avz --delete -e "ssh -i ~/.ssh/visa_platform_hostinger -p 65002" \
  public/build/ u508116592@157.173.223.128:~/domains/geninnovations.net/public_html/visa/build/
```

If `index.php`, `.htaccess`, `favicon.ico`, or `robots.txt` ever change in `public/`, re-copy those specific files into the web root too — they are not part of the `build/` sync and were placed manually (§2).

### First-deploy-only steps (already done, kept for reference)

```bash
git clone https://github.com/genesis12tech/visa_platform.git ~/visa_platform_app
cd ~/visa_platform_app
composer config -g platform.php 8.3.0
composer install --no-dev --optimize-autoloader
# .env written directly (not copied from .env.example) with production values — see §7
php artisan key:generate --force
```

`php artisan storage:link` is **not run and not needed** — `exec()` is disabled in this PHP environment (common shared-hosting hardening), which breaks the command, and this project doesn't use Laravel's public-disk symlink pattern anyway: per FR-DM-07/BR-09, documents are only ever served through authorized controller streams with short-lived signed URLs, never the naive `storage/app/public` → `public/storage` link.

## 6. Database access — Remote MySQL

Hostinger's managed MySQL enforces a host allow-list independent of the password. Currently set to `%` (any host) on `u508116592_visa_db` — a deliberate, **temporary** choice made because this is explicitly the test database (`u508116592_visatest`) with no real applicant data yet. **Scope this down to specific IPs before any production-like data touches this database.** (hPanel → Databases → the database → Remote MySQL.)

## 7. Production `.env` — deltas from local dev

| Setting | Local (current) | Production (deployed) |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` — **hard requirement, NFR-S-05**: no stack traces to users |
| `APP_URL` | `http://localhost:8000` | `http://visa.geninnovations.net` |
| `DB_CONNECTION` | `sqlite` (temporary local stand-in) | `mysql`, real Hostinger DB (`srv683.hstgr.io`) |
| `LOG_LEVEL` | `debug` | `error` |
| `MAIL_*` | Mailtrap sandbox | Same Mailtrap sandbox — production sender (Resend) deferred until a domain-backed sender is set up |
| `SENTRY_LARAVEL_DSN` | blank | Still blank — no Sentry project created yet |
| `AWS_*` | blank | Still blank — no AWS account created yet; `FILESYSTEM_DISK` stays `local` |
| `STRIPE_*` | blank | Still blank — deferred, user will provide when needed |

## 8. The Stage 1 exit check (Implementation_plan.md §4, S1.4 — "the skeleton test") — PASSED 2026-08-15

Verified in the deployed environment, not just locally:

1. A job dispatched onto the `database` queue (from a real PHP file, not `tinker --execute` — closures dispatched from tinker's eval context fail to serialize; a real file works correctly)
2. `queue:work` processed it and wrote a value into the `database` cache table
3. Read back successfully

This proves PHP, the queue, and MySQL are correctly wired together on the real host. Confirmed separately: HTTPS access to `https://visa.geninnovations.net` returns the Laravel welcome page (200), built CSS/JS assets load (200), and the `/up` health-check route responds (200).

## 9. Rollback

Single host, no blue/green setup at this stage: `git log` to find the last good commit, `git checkout <sha>` in `~/visa_platform_app`, re-run `composer install --no-dev --optimize-autoloader` and the cache-clearing artisan commands, re-sync `public/build/` to both locations if the rollback crosses a frontend change. A more robust rollback (tagged releases, atomic symlink swap) is worth adding once Stage 1 is stable — not before.
