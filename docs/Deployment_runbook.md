# Deployment Runbook — Hostinger

| Field | Value |
|---|---|
| **Purpose** | Get the app from a git push to actually running on Hostinger. Translates Implementation_plan.md's Vercel-oriented S1.2–S1.3 to this project's single-host architecture (see CLAUDE.md "Hosting"). |
| **Status** | Written, **not yet executed** — blocked on SSH access credentials (see §4). |
| **Target** | Hostinger shared/cloud hosting, SSH access confirmed available (hPanel → Advanced → SSH Access), PHP 8.3 |

---

## 1. One-time server setup (via hPanel, before the first deploy)

1. **Enable SSH access**: hPanel → Advanced → SSH Access. Note the SSH host, port (Hostinger commonly uses a non-standard port, not always 22), and username.
2. **Select PHP 8.3** for the site: hPanel → Advanced → PHP Configuration (or MultiPHP Manager). Must match the `"php": "8.3.*"` pin in `composer.json` exactly.
3. **Set the document root** to the application's `public/` directory, *not* the application root. Hostinger's own guidance: keep the Laravel application outside the public web root and point the domain's document root at `<app-path>/public` (hPanel → Websites → your domain → Document Root, or equivalent). This is what keeps `.env`, `app/`, `config/`, etc. unreachable from the browser — critical, since this system stores encrypted PII and the whole point of BR-09/FR-DM-01 (private storage only) is undermined if the wrong folder is web-exposed.
4. **Composer**: confirm it's available over SSH (`composer --version`). Hostinger's shared PHP hosting generally ships Composer; if not, `curl` the installer per Composer's own docs.
5. **Cron Jobs**: hPanel → Advanced → Cron Jobs. Add two entries, both every minute (`* * * * *`), since this project has no Horizon/Redis (see CLAUDE.md — database-backed queue instead):
   ```
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   * * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
   ```
   `--max-time=50` keeps the queue worker from overlapping into the next minute's cron tick.

## 2. Node/npm on the server — deliberately not relied on

Shared hosting frequently has no Node.js runtime, or a restricted one. Rather than depend on that, **frontend assets are built locally (or later, in CI — Stage 1 S1.5) and the compiled `public/build/` output is deployed alongside the PHP code**, not built on the server. `npm`/`vite` never need to run on Hostinger itself.

## 3. Deployment steps (first deploy and every subsequent one)

```bash
# Local machine — build assets first
npm run build

# SSH to Hostinger
ssh -p <port> <user>@<host>

# On the server, first time only:
git clone https://github.com/genesis12tech/visa_platform.git <app-path>
cd <app-path>
composer install --no-dev --optimize-autoloader
cp .env.example .env               # then edit .env with real production values — see §5
php artisan key:generate
php artisan storage:link

# On the server, every deploy:
cd <app-path>
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart          # in case a queued job references code that just changed
```

Separately, sync the locally-built `public/build/` directory to the server (rsync over SSH, or scp) since it's git-ignored and never built server-side:

```bash
rsync -avz --delete public/build/ <user>@<host>:<app-path>/public/build/
```

## 4. What's blocking this from actually running

**SSH access details are not yet in `DataCredential.txt`.** Needed: SSH host, port, username, and either a password or (preferred) an SSH key. Please add these the same way as the DB credentials.

**Domain/URL is also unresolved** — per an earlier conversation, no custom domain is available yet. Hostinger accounts typically expose a temporary address before a real domain is pointed at the account; if that's what we're using for now, the exact address is needed for `APP_URL` and for confirming the app is actually reachable after deploy. If there's no reachable address at all yet, the deploy can still happen and be verified over SSH (curl `localhost` from the server, check `php artisan about`), but it won't be publicly visible until a domain/temporary address is wired up.

## 5. Production `.env` deltas from the local dev `.env`

| Setting | Local (current) | Production |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` — **hard requirement, NFR-S-05**: no stack traces to users |
| `DB_CONNECTION` | `sqlite` (temporary stand-in) | `mysql`, pointed at the Hostinger DB in `DataCredential.txt` |
| `LOG_CHANNEL` | `stack` | `stack` is fine; ensure `storage/logs` is writable |
| `MAIL_*` | Mailtrap sandbox | Same Mailtrap sandbox is fine for now — production sender (Resend) is deferred until a domain exists, per earlier conversation |
| `SENTRY_LARAVEL_DSN` | blank | Needs a real Sentry project DSN — not yet created |
| `AWS_*` | blank | Needs a real AWS account — not yet created |
| `STRIPE_*` | blank | Deferred — user will provide when needed |

## 6. The Stage 1 exit check (Implementation_plan.md §4, S1.4 — "the skeleton test")

Once deployed, prove the whole path works **in the deployed environment**, not just locally:

1. A route dispatches a trivial job onto the `database` queue.
2. The cron-triggered `queue:work` picks it up within a minute and writes a row to MySQL.
3. A second route reads that row back.

This is the walking-skeleton proof that PHP, the queue, cron, and MySQL are all correctly wired together on the real host — everything after this stage builds on that assumption holding.

## 7. Rollback

Since this is a single host with no blue/green setup at this stage: `git log` to find the last good commit, `git checkout <sha>`, re-run `composer install --no-dev --optimize-autoloader` and the cache-clearing artisan commands. A more robust rollback (tagged releases, atomic symlink swap) is worth adding once Stage 1 is stable — not before.
