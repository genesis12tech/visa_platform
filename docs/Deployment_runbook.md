# Deployment Runbook — Hostinger

| Field | Value |
|---|---|
| **Purpose** | Get the app from a git push to actually running on Hostinger. Translates Implementation_plan.md's Vercel-oriented S1.2–S1.3 to this project's single-host architecture (see CLAUDE.md "Hosting"). |
| **Status** | **Live.** Redeployed 2026-08-20 at `https://visa.geninnovations.net` after a full account reset (see §0). Originally deployed 2026-08-15 under a different topology — superseded, see §1. |
| **Target** | Hostinger Cloud Startup shared hosting, SSH access (hPanel → Advanced → SSH Access), PHP 8.3.30 (account default) |

---

## 0. 2026-08-20 incident — full account reset

A WordPress reinstall for the main domain (`geninnovations.net`) turned out to reset the **entire hosting
account**, not just the WordPress site: `~/visa_platform_app` (the whole prior Laravel deployment) was gone,
`public_html/visa` (the web root) was emptied, `~/.ssh/authorized_keys` no longer had our deploy key, and both
cron jobs were gone. The MySQL database (`u508116592_visa_db`) and its user were also recreated fresh (new
password required, new physical host — see §6) rather than surviving the reset.

Nothing was actually lost — the codebase lives in GitHub, not on the server — but this was a full from-scratch
redeploy, done directly into the new target layout requested at the time (§1), rather than a simple restore of
the old one. **Lesson for next time:** if the main domain's hosting ever needs reinstalling again, treat the
whole account as at-risk, not just that one site, and re-verify SSH keys, cron jobs, and DB credentials
afterward rather than assuming they survived.

## 1. Server layout — read this before touching anything

**As of 2026-08-20, the app root and the web root are the same directory** — this replaced the original
split-directory approach:

| Path | What it is |
|---|---|
| `/home/u508116592/domains/geninnovations.net/public_html/visa` | **Both** the full application (git checkout, `vendor/`, `.env`, everything) **and** the web-facing root for `visa.geninnovations.net`. |

### Why not a split directory anymore

The original approach (documented here through 2026-08-19) kept the full app entirely outside `public_html`,
with only a hand-written `index.php` and built assets living in the actual web root — because this Hostinger
plan can't redirect a subdomain's document root to a `public/` subfolder (confirmed by testing in Stage 1, not
assumed). That worked, but added a second copy of built assets to keep in sync on every deploy.

The 2026-08-20 redeploy uses a different, equally standard technique that gets the same safety property with
one copy of everything: a **root `.htaccess` that rewrites every request into `public/`**, so the document
root can stay pinned at `public_html/visa` (unavoidable on this plan) while nothing outside `public/` is ever
actually served.

```apache
# public_html/visa/.htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ /public/$1 [L]
</IfModule>
```

### Agent and staff subdomains (added 2026-08-20, wired 2026-08-21)

`visa-agent.geninnovations.net` and `visa-staff.geninnovations.net` (Backend_schema.md §11.1's other two
guards) are **separate Hostinger subdomains with their own fixed web roots**
(`~/domains/geninnovations.net/public_html/visa-agent` and `.../visa-staff`), but there is still only **one**
Laravel app instance — `Route::domain()` in `bootstrap/app.php` differentiates which guard's routes apply by
Host header (routes/agent.php, routes/staff.php). Because their web roots are physically separate directories
from where the app actually lives, they **cannot** use the rewrite-to-`/public` trick above (that only works
when the web root and the app root are the same directory, as they are for the primary `visa` domain) — they
need the older split-directory pattern instead: a hand-written `index.php` bootstrapping the shared app by
absolute path, **plus** a standard Laravel front-controller `.htaccess`:

```php
// public_html/visa-agent/index.php (public_html/visa-staff/index.php is identical except the comment)
<?php
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
$appPath = '/home/u508116592/domains/geninnovations.net/public_html/visa';
if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) { require $maintenance; }
require $appPath.'/vendor/autoload.php';
$app = require_once $appPath.'/bootstrap/app.php';
$app->handleRequest(Request::capture());
```

```apache
# public_html/visa-agent/.htaccess and public_html/visa-staff/.htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

**The `.htaccess` is not optional** — without it, `/` resolves via Apache's `DirectoryIndex` and works, but any
other path (`/login`, `/mfa/enroll`, ...) doesn't correspond to a real file and 404s *before ever reaching
Laravel* (briefly surfaced as WordPress's own themed 404 page — the domain root's WordPress `.htaccess` was
catching the fall-through). This is the standard Laravel `public/.htaccess` content, unmodified.

**No built frontend assets live in these two directories** — current auth views (login, MFA, etc.) don't
reference any, so this is a known, accepted gap rather than a real one right now. It will need addressing (most
likely: point asset URLs at the primary domain regardless of which portal is being viewed, rather than
duplicating `public/build/` into three places) once S2.10's design system actually ships CSS/JS to these
portals.

**Discovered while wiring this up**: production was silently 3 commits behind — the app directory's `git pull`
had only ever been run once, during the initial 2026-08-20 redeploy, and never again despite two full stages
(S2.6, S2.7) shipping since. There's exactly one app checkout to keep in sync (`public_html/visa`) regardless
of how many subdomains front it — remember to actually run the deploy steps in §5, not just assume `git push`
was enough.

A request for `/.env` or `/vendor/autoload.php` gets rewritten to `/public/.env` / `/public/vendor/autoload.php`
— neither exists under `public/`, so Apache 404s instead of serving the real file. `public/index.php` is now
Laravel's **standard, unmodified** entry point (no custom bootstrap needed), since `app/`, `vendor/`, and
`public/` are siblings again exactly as Laravel expects.

**Verified empirically after deploy, not assumed** (BR-09/FR-DM-01 — no application code, `.env`, or config may
ever be web-exposed):

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://visa.geninnovations.net/.env                  # 404
curl -s -o /dev/null -w "%{http_code}\n" https://visa.geninnovations.net/vendor/autoload.php    # 404
curl -s -o /dev/null -w "%{http_code}\n" https://visa.geninnovations.net/composer.json          # 404
curl -s -o /dev/null -w "%{http_code}\n" https://visa.geninnovations.net/artisan                # 404
curl -s -o /dev/null -w "%{http_code}\n" https://visa.geninnovations.net/                        # 200
curl -s -o /dev/null -w "%{http_code}\n" https://visa.geninnovations.net/up                      # 200
```

## 2. Cron jobs (hPanel → Advanced → Cron Jobs) — reconfigured and verified 2026-08-20

No `crontab` CLI access on this host (confirmed again after the reset — `crontab: command not found` over
SSH) — hPanel-managed only. The Minute/Hour/Day/Month/Weekday selectors do not default to "every"/`*` on their
own; each must be explicitly set, or use the "Every Minute" preset. **First save after the reset landed as
`0 * * * *` (hourly) instead of every minute — the same mistake as Stage 1's first attempt** — caught by
reviewing the saved job list, not assumed correct. Two jobs, both `* * * * *`:

| | Command (after the fixed `/usr/bin/php /home/u508116592/` prefix) |
|---|---|
| Scheduler | `domains/geninnovations.net/public_html/visa/artisan schedule:run` |
| Queue worker | `domains/geninnovations.net/public_html/visa/artisan queue:work --queue=emails,default --stop-when-empty --max-time=50` |

**Updated 2026-08-22 (S2.11):** every real notification now routes to a queue literally named
`emails` (`App\Notifications\TemplatedNotification`'s constructor calls `onQueue('emails')`), not
Laravel's implicit `default` queue. `queue:work` without an explicit `--queue=` flag only drains
`default` — so **the saved hPanel cron job for the queue worker must be updated to add
`--queue=emails,default`**, or every notification dispatched from this point on sits in the `jobs`
table forever, never processed. Hostinger has no `crontab` CLI on this host (see above), so this can
only be changed through hPanel's cron job editor by hand — not something a deploy step can apply
automatically. `default` is kept alongside `emails` since nothing currently distinguishes the two in
practice, but the explicit list means a future queue name doesn't silently go undrained either.

**Verified 2026-08-20**: dispatched a job from a real file (`dispatch_test.php`, deleted after — see the
closure-serialization note in §8 on why `tinker --execute` doesn't work for this), confirmed a cache key it
should have written was absent immediately after dispatch, then present ~3–4 minutes later once the newly-saved
cron jobs actually started firing (first check came back empty — newly-saved hPanel cron jobs took a few
minutes to start actually running, not instant).

## 3. Node/npm on the server — deliberately not relied on

Shared hosting frequently has no Node.js runtime. Frontend assets are built **locally** (or later, in CI —
Stage 1 S1.5) and the compiled `public/build/` output is synced up separately — `npm`/`vite` never run on
Hostinger itself. Only **one** sync target now (was two, before the layout change):

```bash
npm run build
rsync -avz --delete -e "ssh -i ~/.ssh/visa_platform_hostinger -p 65002" \
  public/build/ u508116592@157.173.223.128:~/domains/geninnovations.net/public_html/visa/public/build/
```

## 4. `composer install` post-script caveat

`exec()`/`proc_open()` are disabled in this PHP environment (shared-hosting hardening — the same restriction
that blocks `storage:link`, see §5). This breaks Composer's auto-run `post-autoload-dump` hook
(`@php artisan package:discover`), which shells out via Symfony Process:

```
In Process.php line 147:
  The Process class relies on proc_open, which is not available on your PHP installation.
```

`composer install` itself still completes correctly — only the post-script fails. Run discovery manually right
after:

```bash
composer install --no-dev --optimize-autoloader
php artisan package:discover --ansi
```

## 5. Deployment steps

```bash
# Local machine — build assets first
npm run build

# SSH to Hostinger
ssh -i ~/.ssh/visa_platform_hostinger -p 65002 u508116592@157.173.223.128

# On the server, every deploy:
cd ~/domains/geninnovations.net/public_html/visa
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan package:discover --ansi     # composer's own post-script can't run this — see §4
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart          # in case a queued job references code that just changed
```

Then, from the local machine, sync the freshly-built assets (one location now — see §3):

```bash
rsync -avz --delete -e "ssh -i ~/.ssh/visa_platform_hostinger -p 65002" \
  public/build/ u508116592@157.173.223.128:~/domains/geninnovations.net/public_html/visa/public/build/
```

`.htaccess` (§1) only needs re-copying if it changes — it's not part of the `build/` sync and isn't
overwritten by `git pull` since it's gitignored-equivalent (kept out of the repo deliberately, written directly
on the server, same as `.env`).

### First-deploy-only steps (2026-08-20 redeploy)

```bash
git clone https://github.com/genesis12tech/visa_platform.git ~/domains/geninnovations.net/public_html/visa
cd ~/domains/geninnovations.net/public_html/visa
composer config -g platform.php 8.3.0
composer install --no-dev --optimize-autoloader
php artisan package:discover --ansi
# .env written directly on the server (not copied from .env.example) with production values — see §7
php artisan key:generate --force
php artisan migrate --force
chmod -R 775 storage bootstrap/cache
# .htaccess written directly on the server — see §1 for contents
```

`php artisan storage:link` is **not run and not needed** — `exec()` is disabled in this PHP environment (§4),
which breaks the command, and this project doesn't use Laravel's public-disk symlink pattern anyway: per
FR-DM-07/BR-09, documents are only ever served through authorized controller streams with short-lived signed
URLs, never the naive `storage/app/public` → `public/storage` link.

## 6. Database access — Remote MySQL

Hostinger's managed MySQL enforces a host allow-list independent of the password. Currently set to `%` (any
host) on `u508116592_visa_db` — a deliberate, **temporary** choice made because this is explicitly the test
database with no real applicant data yet. **Scope this down to specific IPs before any production-like data
touches this database.** (hPanel → Databases → the database → Remote MySQL.)

**Known instability, not fully root-caused across this project's life so far:** the database's actual physical
host (`DB_HOST`) has changed more than once without warning — `srv683.hstgr.io` → `srv1331.hstgr.io` →
`srv1130.hstgr.io` as of the 2026-08-20 redeploy — and the 2026-08-20 account reset recreated the database and
user from scratch with a new password, despite the Remote MySQL `%` entry surviving unchanged. **Symptom to
watch for:** `SQLSTATE[HY000] [1045] Access denied`, with the source IP shown in the error the same either way
— this error covers both a host-allowlist mismatch and a wrong password with an identical message, so don't
assume which one it is; check the DB user's `Created at` date in hPanel (a very recent date is the tell that
credentials were reset) and verify the actual current hostname shown on the Remote MySQL page rather than
trusting whatever was last written to `.env`.

## 7. Production `.env` — deltas from local dev

| Setting | Local (current — MAMP Pro, see CLAUDE.md) | Production (deployed 2026-08-20) |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` — **hard requirement, NFR-S-05**: no stack traces to users |
| `APP_URL` | `http://localhost:8000` | `https://visa.geninnovations.net` |
| `DB_CONNECTION` | `mysql` (MAMP Pro, MySQL 5.7) | `mysql`, real Hostinger DB (`srv1130.hstgr.io` as of 2026-08-20 — see §6) |
| `LOG_LEVEL` | `debug` | `error` |
| `BLIND_INDEX_KEY` | Local-only value | **Separately generated**, never copied from local (Backend_schema.md §13.2) |
| `MAIL_*` | Mailtrap sandbox | Same Mailtrap sandbox — production sender (Resend) deferred until a domain-backed sender is set up |
| `SENTRY_LARAVEL_DSN` | blank | Still blank — no Sentry project created yet |
| `AWS_*` | blank | Still blank — no AWS account created yet; `FILESYSTEM_DISK` stays `local` |
| `STRIPE_*` | blank | Still blank — deferred, user will provide when needed |

## 8. Dispatching a real test job (for verifying the queue/cron pipeline)

`tinker --execute` with an inline closure fails to serialize onto the queue — closures dispatched from an
eval'd context (not a real file) can't be reconstructed by the worker, since serialization needs to re-read the
original source file:

```
Call to a member function bindTo() on null
```

Write dispatch logic to a real temporary `.php` file on the server instead, run it directly with `php`, then
delete it *after* confirming the job processed (deleting it before processing causes the same failure).

## 9. Rollback

Single host, no blue/green setup at this stage: `git log` to find the last good commit, `git checkout <sha>`
in `~/domains/geninnovations.net/public_html/visa`, re-run `composer install --no-dev --optimize-autoloader`
and the cache-clearing artisan commands, re-sync `public/build/` if the rollback crosses a frontend change. A
more robust rollback (tagged releases, atomic symlink swap) is worth adding once the deployment topology is
stable — not before.
