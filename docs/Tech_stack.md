# Tech Stack — Locked Versions
## Visa Application System

| Field | Value |
|---|---|
| **Version** | 1.0 |
| **Date** | 13 August 2026 |
| **Purpose** | Authoritative dependency lock. Input document for Claude Code. |
| **Companion documents** | PRD v1.0; App Flow v1.0; Screen UI Specs v1.0; Implementation Prompts v1.0 |
| **Supersedes** | Stack assumptions in all prior documents (PostgreSQL, Filament 4, DDD/Action classes) |

---

## 1. Decision Record

Three decisions were taken that reverse assumptions in the earlier documents. They are recorded here with their consequences so nobody rediscovers them mid-build.

| # | Decision | Reverses | Consequence |
|---|---|---|---|
| D-1 | **MySQL 8** | PostgreSQL + JSONB | No partial unique indexes; `json` instead of `jsonb`; different indexing on JSON paths. Workarounds in §7. |
| D-2 | **Split hosting** — Vercel serverless HTTP tier, separate worker host | Single Forge/VPS deployment | Two deploy targets, two env sets, code sync burden, no `gd`/`imagick` on the web tier. §5. |
| D-3 | **No Filament** — Blade + Livewire 3 everywhere | Filament 4 officer/admin panels | ~27 admin/officer screens hand-built. Replaced by Livewire + `rappasoft/laravel-livewire-tables`. §8. |
| D-4 | **Strict MVC** | DDD `app/Domain/` + Action classes | Controllers, Models, Requests, Policies. Six operations still need a home; see §9.2. |
| D-5 | **Laravel 12 + PHP 8.3** | (Resolves PRD OD-1) | Laravel 13 and PHP 8.4 exist; you are one major behind on both, deliberately. Blocks Pest 5. §3.3. |

**D-3 has a defensible upside worth stating.** Filament 4 is recent enough that model training coverage is thin, and its custom-action authorisation model is a known footgun. Plain Blade + Livewire 3 has vastly more training data behind it, which is exactly the "maximum Claude Code compatibility" you asked for. The cost is build volume, not quality.

---

## 2. Verification Status

Versions below are marked:

- **✅ Verified 13 Aug 2026** — checked against the registry or repository today
- **⚠ Verify at install** — major version is correct and constraint-safe; exact patch must be confirmed by the resolution pass in §12

Do not treat ⚠ entries as fabricated-precise. Run §12 before the first commit; it writes the true lock and fails the build on drift.

---

## 3. Runtime

### 3.1 Core runtime

| Component | Version | Status | Constraint |
|---|---|---|---|
| PHP | `8.3.x` | ✅ | Fixed by D-5 |
| Node.js | `22.x` | ✅ | Required by `vercel-php`; do not use 20 or 24 |
| Composer | `2.8.x` | ⚠ | |
| MySQL | `8.0.x` LTS | ✅ | Not 8.4 — see §7.1 |
| Redis | `7.2.x` | ⚠ | Managed service; must be reachable from both tiers |

### 3.2 Framework

| Package | Exact version | Status |
|---|---|---|
| `laravel/framework` | `12.66.0` | ✅ Latest 12.x as of today |
| `livewire/livewire` | `3.8.4` | ✅ Latest 3.x as of today |

Laravel 13 (`13.25.0`) and Livewire 4 (`4.4.0`) are both current. Laravel 12 receives bug fixes through 2026 and security fixes into 2027; Livewire 3.8.x is still receiving fixes alongside 4.x. Staying on 12/3 is supportable for this build cycle but is a deliberate, dated choice — revisit before any 2027 work.

### 3.3 The Pest constraint — read before pinning tests

**Pest 5 cannot be used on this stack.** Pest 5.1.0 requires `php: ^8.4`, and `pestphp/pest-plugin-laravel` v5.0.1 requires `laravel/framework: ^13.23.0`. Both are incompatible with D-5.

Pin **Pest 4.x**. If the build later moves to PHP 8.4 + Laravel 13, Pest 5's Test Impact Analysis and agent plugin become available and are worth the upgrade — the agent plugin in particular is designed for exactly the Claude Code workflow you're running. Note it as a future decision, not a current option.

---

## 4. Composer Dependencies

### 4.1 `composer.json` — production

```json
{
  "require": {
    "php": "8.3.*",
    "laravel/framework": "12.66.0",
    "livewire/livewire": "3.8.4",
    "laravel/horizon": "5.*",
    "laravel/sanctum": "4.*",
    "laravel/tinker": "2.*",

    "spatie/laravel-permission": "6.*",
    "spatie/laravel-activitylog": "4.*",
    "spatie/laravel-data": "4.*",

    "rappasoft/laravel-livewire-tables": "3.*",
    "barryvdh/laravel-dompdf": "3.*",
    "maatwebsite/excel": "3.1.*",
    "stripe/stripe-php": "16.*",
    "league/flysystem-aws-s3-v3": "3.*",
    "intervention/image": "3.*",
    "nesbot/carbon": "3.*",
    "predis/predis": "2.*",
    "sentry/sentry-laravel": "4.*",
    "pragmarx/google2fa-laravel": "2.*",
    "bacon/bacon-qr-code": "3.*",
    "propaganistas/laravel-phone": "5.*",
    "spatie/laravel-backup": "9.*"
  },
  "require-dev": {
    "pestphp/pest": "4.*",
    "pestphp/pest-plugin-laravel": "4.*",
    "laravel/pint": "1.*",
    "larastan/larastan": "3.*",
    "nunomaduro/collision": "8.*",
    "fakerphp/faker": "1.*",
    "mockery/mockery": "1.*",
    "barryvdh/laravel-ide-helper": "3.*"
  }
}
```

### 4.2 Package rationale and status

| Package | Purpose | Status | Notes |
|---|---|---|---|
| `laravel/horizon` 5.x | Queue monitoring | ⚠ | **Worker host only.** Dashboard is not deployed to Vercel. |
| `laravel/sanctum` 4.x | API tokens | ⚠ | Deferred to Phase 2; installed now to avoid a later migration |
| `spatie/laravel-permission` 6.x | RBAC | ⚠ | v6 supports Laravel 10–12 |
| `spatie/laravel-activitylog` 4.x | Audit trail | ⚠ | Supplements the custom `audit_logs` table; does not replace it |
| `spatie/laravel-data` 4.x | Typed DTOs | ⚠ | Used for form-schema objects and view models only — not as a services layer |
| `rappasoft/laravel-livewire-tables` 3.x | Admin data tables | ⚠ | **The Filament replacement.** Livewire 3 compatible, widely used, long-established |
| `barryvdh/laravel-dompdf` 3.x | PDF generation | ⚠ | **Worker host only** — no Browsershot, which needs Chrome |
| `maatwebsite/excel` 3.1.x | XLSX/CSV export | ⚠ | **Worker host only** — memory-heavy |
| `stripe/stripe-php` 16.x | Payments | ⚠ | Direct SDK, not Cashier — Cashier is subscription-oriented and wrong for one-off consular fees |
| `intervention/image` 3.x | Image validation, thumbnails | ⚠ | **Worker host only** — requires `gd` or `imagick`, neither present on Vercel. See §5.3 |
| `predis/predis` 2.x | Redis client | ⚠ | Pure PHP. Preferred over phpredis on Vercel where extension control is limited |
| `pragmarx/google2fa-laravel` + `bacon/bacon-qr-code` | Staff MFA | ⚠ | Laravel Fortify is heavier than needed given no Filament auth |
| `spatie/laravel-backup` 9.x | DB and file backups | ⚠ | **Worker host only** — scheduled |

### 4.3 Explicitly excluded

| Package | Why not |
|---|---|
| `filament/filament` | D-3 |
| `laravel/cashier` | Subscription-oriented; one-off fees don't fit its model |
| `spatie/browsershot` | Requires a Chrome binary; unavailable on Vercel, heavy on the worker |
| `spatie/laravel-medialibrary` | Its abstraction conflicts with the explicit version/scan model in the PRD |
| `laravel/octane` | Incompatible with serverless; premature regardless |
| `laravel/scout` | Deferred per PRD non-goals |
| `laravel/pennant` | Feature flags deferred; config-driven flags suffice for v1 |
| `pestphp/pest` 5.x | §3.3 |

---

## 5. Hosting Architecture — Split Deployment

### 5.1 Topology

```
                    ┌──────────────────────────┐
   applicants  ───▶ │  VERCEL                  │
   agents           │  vercel-php@0.7.4        │
                    │  HTTP tier only          │
                    │  · applicant portal      │
                    │  · agent portal          │
                    │  · officer/admin panels  │
                    │  · public tracking       │
                    │  · Stripe webhook intake │
                    └──────────┬───────────────┘
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                ▼
      ┌──────────────┐ ┌─────────────┐ ┌──────────────┐
      │ MySQL 8      │ │  Redis 7.2  │ │  S3 private  │
      │ (managed)    │ │  (managed)  │ │  bucket      │
      └──────────────┘ └──────┬──────┘ └──────────────┘
                               │ queues
                    ┌──────────▼───────────────┐
                    │  WORKER HOST (VPS)       │
                    │  Ubuntu 24.04 + PHP 8.3  │
                    │  · Horizon + workers     │
                    │  · scheduler (cron)      │
                    │  · PDF generation        │
                    │  · virus scanning        │
                    │  · image processing      │
                    │  · exports, backups      │
                    │  · Horizon dashboard     │
                    └──────────────────────────┘
```

**The same codebase deploys to both targets.** Vercel serves HTTP; the worker host runs `php artisan horizon` and `cron`. They must deploy from the same commit — a version skew between tiers means a job serialised by the web tier can fail to deserialise on the worker.

### 5.2 Vercel configuration

| Item | Value | Status |
|---|---|---|
| Runtime | `vercel-php@0.7.4` | ✅ **This is the PHP 8.3 build** |
| Node version | `22.x` | ✅ |
| Function memory | `1024` MB | — |
| `maxDuration` | `60` s (Pro plan) | — |
| Region | Single region, co-located with MySQL and Redis | — |

`vercel.json`:

```json
{
  "version": 2,
  "framework": null,
  "regions": ["bom1"],
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.7.4",
      "memory": 1024,
      "maxDuration": 60
    }
  },
  "routes": [
    { "src": "/build/(.*)", "dest": "/public/build/$1" },
    { "src": "/(.*)", "dest": "/api/index.php" }
  ],
  "outputDirectory": "public"
}
```

**Do not pin to `vercel-php@0.9.0` or `@0.8.0`.** Those are PHP 8.5 and 8.4 respectively and will silently move you off 8.3, breaking the Pest 4 and Laravel 12 assumptions.

`api/index.php`:

```php
<?php
require __DIR__ . '/../public/index.php';
```

### 5.3 PHP extension availability — the constraint that shapes the split

The `vercel-php` runtime provides: `apcu, bcmath, brotli, bz2, calendar, ctype, curl, date, dom, ds, exif, fileinfo, filter, ftp, geoip, gettext, hash, iconv, igbinary, imap, intl, json, libxml, lua, mbstring, mongodb, msgpack, mysqli, mysqlnd, openssl, pcntl, pcre, PDO, pdo_mysql, pdo_pgsql, pdo_sqlite, pgsql, Phar, protobuf, readline, redis, Reflection, session, SimpleXML, soap, sockets, sodium, SPL, sqlite3, tokenizer, uuid, xml, xmlreader, xmlwriter, xsl, Zend OPcache, zlib, zip`.

| Extension | Vercel | Worker | Consequence |
|---|---|---|---|
| `bcmath` | ✅ | ✅ | Money arithmetic works on both tiers |
| `pdo_mysql` | ✅ | ✅ | |
| `redis` | ✅ | ✅ | |
| `intl` | ✅ | ✅ | Localisation, timezone handling |
| `zip` | ✅ | ✅ | |
| **`gd`** | ❌ | ✅ install | **Image validation and thumbnails are worker-only** |
| **`imagick`** | ❌ | ✅ optional | Same |

**This is not a preference — it is a hard constraint.** PRD FR-DM-03 requires validating image dimensions on upload. On Vercel the request can accept the file and validate MIME by content inspection (`fileinfo` is present), but dimension checks, thumbnails, and any image transformation must be deferred to a queued job on the worker. Specify uploads as: accept → content-type validate → store → queue `ProcessDocumentImage` → worker validates dimensions and may reject asynchronously.

### 5.4 Serverless constraints on the web tier

| Constraint | Handling |
|---|---|
| Read-only filesystem except `/tmp` | All Laravel cache paths redirected to `/tmp` via env (§10.2) |
| No persistent connections | MySQL and Redis connect per invocation; set conservative pool limits at the managed service |
| Cold start ~250 ms, warm ~5 ms | Acceptable; monitor p95 against the PRD budget of 500 ms |
| No long-running processes | Horizon, scheduler, and all workers live on the worker host |
| No local session files | `SESSION_DRIVER=redis` mandatory |
| No local logs | `LOG_CHANNEL=stderr` on Vercel |
| Function timeout 60 s | Stripe webhook must acknowledge in under 500 ms and defer to queue — already specified in prompt M4.3 |

### 5.5 Worker host

| Item | Version | Status |
|---|---|---|
| OS | Ubuntu 24.04 LTS | ✅ |
| PHP | 8.3.x with `gd`, `bcmath`, `redis`, `pdo_mysql`, `intl`, `zip`, `exif` | — |
| Process manager | Supervisor 4.2.x | ⚠ |
| Provisioning | Laravel Forge, or Ansible if self-managed | — |
| Scheduler | System cron → `php artisan schedule:run` every minute, under a distributed lock | — |

Horizon supervisors per PRD queue names: `high`, `default`, `emails`, `documents`, `pdfs`, `reports`.

---

## 6. Frontend Dependencies

### 6.1 `package.json`

```json
{
  "type": "module",
  "devDependencies": {
    "vite": "6.*",
    "laravel-vite-plugin": "1.*",
    "tailwindcss": "3.4.*",
    "@tailwindcss/forms": "0.5.*",
    "autoprefixer": "10.4.*",
    "postcss": "8.4.*",
    "alpinejs": "3.14.*",
    "@alpinejs/focus": "3.14.*",
    "axios": "1.*"
  },
  "engines": {
    "node": "22.x"
  }
}
```

| Package | Status | Notes |
|---|---|---|
| `tailwindcss` 3.4.x | ⚠ | **Not 4.x.** Tailwind 4 changed the config model substantially; 3.4 has far broader training coverage — directly relevant to D-3's rationale |
| `alpinejs` 3.14.x | ⚠ | Bundled with Livewire 3; pinned explicitly for `@alpinejs/focus`, needed for the session-warning modal focus trap |
| `@alpinejs/focus` | ⚠ | Required by the modal focus-trap spec in Screen UI Specs §3.6 |

### 6.2 Fonts

Self-hosted, not CDN-loaded — a government service should not leak applicant requests to a third party.

| Font | Version | Licence | Status |
|---|---|---|---|
| Public Sans | 2.001 | OFL 1.1 | ⚠ Confirm per UI-7 |
| Source Serif 4 | 4.004 | OFL 1.1 | ⚠ Confirm per UI-7 |
| IBM Plex Mono | 3.003 | OFL 1.1 | ⚠ Tracking numbers only |

---

## 7. MySQL 8 — Deltas From the PostgreSQL Assumption

### 7.1 Version choice

Pin **MySQL 8.0.x**, not 8.4. 8.0 has the longest-established Laravel driver behaviour and the widest deployment base. 8.4 changed default authentication plugin handling and removed several deprecated options; the compatibility surface is thinner.

### 7.2 JSON columns

`jsonb` does not exist. Every `jsonb` column in the earlier documents becomes `json`.

| Concern | PostgreSQL | MySQL 8 |
|---|---|---|
| Type | `jsonb` (binary, indexed) | `json` (validated text) |
| Indexing | GIN index directly | Generated column + index on it |
| Path query | `->>` operators | `JSON_EXTRACT` / `->>` |
| Storage | Compressed binary | Text |

Where a JSON path is queried frequently — form-template section keys, document `condition_rules` — add a stored generated column and index that, rather than indexing JSON directly.

### 7.3 Partial unique indexes — the one real blocker

Prompt M2.1 specifies "a partial unique index enforcing at most one `is_active = true` per `visa_type_id`". **MySQL 8 has no partial indexes.** The workaround uses a generated column that is `NULL` when inactive, exploiting MySQL's allowance of multiple `NULL`s in a unique index:

```php
Schema::create('form_templates', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('visa_type_id')->constrained()->restrictOnDelete();
    $table->unsignedInteger('version');
    $table->string('name');
    $table->json('schema');
    $table->boolean('is_active')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    $table->unique(['visa_type_id', 'version']);
});

// Generated column + unique index: NULL when inactive, so many inactive
// rows coexist while at most one active row per visa type is possible.
DB::statement("
    ALTER TABLE form_templates
    ADD COLUMN active_visa_type_id CHAR(26)
        GENERATED ALWAYS AS (IF(is_active, visa_type_id, NULL)) STORED,
    ADD UNIQUE INDEX form_templates_one_active (active_visa_type_id)
");
```

Apply the same pattern anywhere the earlier documents assumed a partial index.

### 7.4 Other deltas

| Concern | Handling |
|---|---|
| `CHECK` constraints | Supported and enforced in MySQL 8.0.16+. The `booked_count <= capacity` check from M6.1 works. |
| Row locking for booking | `SELECT ... FOR UPDATE` works. The M6.2 concurrency model is unchanged. |
| ULID columns | `CHAR(26)` with `utf8mb4_bin` collation for correct comparison |
| Full-text search | `FULLTEXT` index on InnoDB is adequate for v1; Scout remains deferred |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` throughout — required for the applicant name set |
| Transaction isolation | `READ-COMMITTED` recommended over MySQL's `REPEATABLE-READ` default, to reduce gap-lock contention on the booking path |

---

## 8. Replacing Filament

Roughly 27 officer and admin screens previously delegated to Filament resources now need building.

| Filament capability | Replacement |
|---|---|
| Resource list tables | `rappasoft/laravel-livewire-tables` 3.x — sorting, filtering, search, bulk actions, pagination |
| Resource forms | Blade forms + Form Requests + Livewire components |
| Relation managers | Nested Livewire components on a parent view |
| Custom actions | Explicit controller routes + Policy checks |
| Widgets | Blade partials reading from the metrics read models |
| Panel auth | Standard Laravel auth + route middleware + Policies |
| Notifications | Livewire toast component |

**The authorisation upside.** Filament's custom actions do not auto-authorise, which is why prompt M1.5 needed an action-enumeration test. With plain controllers, authorisation is a `$this->authorize()` call in a controller method — conventional, obvious, and far easier for a model to get right. Keep the enumeration test anyway, retargeted at controller methods.

**The cost.** Budget roughly 1.5–2× the Filament build time for M1.5, M5.1, M5.2, and the whole ADM range. This is the single largest schedule consequence in this document.

---

## 9. Application Structure — Strict MVC

### 9.1 Directory layout

```
app/
  Http/
    Controllers/
      Applicant/     ApplicationController, SectionController,
                     DocumentController, PaymentController,
                     AppointmentController, ProfileController
      Agent/         DashboardController, ClientController,
                     LinkageController, AgentPaymentController
      Officer/       QueueController, CaseController,
                     DocumentReviewController, DecisionController
      Admin/         UserController, VisaTypeController, FeeController,
                     FormTemplateController, LocationController,
                     ReportController, AuditController
      Public/        TrackingController, CatalogueController
      Webhooks/      StripeWebhookController
    Requests/        one per write operation
    Middleware/
    Resources/
  Models/            User, ApplicantProfile, Country, VisaType, VisaFee,
                     FormTemplate, VisaApplication, ApplicationAnswer,
                     ApplicationSnapshot, ApplicationStatusHistory,
                     DocumentType, ApplicationDocument, DocumentVersion,
                     Payment, PaymentItem, PaymentWebhookEvent, Invoice,
                     Refund, ServiceLocation, AppointmentSlot, Appointment,
                     Agency, AgentApplicantLink, ReviewNote, AuditLog
  Policies/          one per sensitive model
  Services/          see §9.2 — six classes, no more
  Jobs/
  Events/
  Listeners/
  Notifications/
  Enums/
  Casts/
  Livewire/
    Applicant/  Agent/  Officer/  Admin/  Shared/
  Support/
    Money/
```

### 9.2 Where cross-model logic lives

Strict MVC has no natural home for operations that span several models inside one transaction. Six exist in this system, and putting them in controllers would produce exactly the fat controllers MVC is meant to avoid. They go in `app/Services/` as a deliberately capped list:

| Service | Why it cannot be a model method |
|---|---|
| `SubmissionGuard` | Aggregates sections, documents, and payment state. Must be the **single source of truth** consumed by both the controller and the hub view — the parity rule from Screen UI Specs §13 still applies. |
| `ApplicationSubmitter` | Snapshot + fee resolution + two status transitions in one transaction |
| `StripeWebhookProcessor` | Idempotency, ledger write, transition, invoice, PDF dispatch |
| `ApprovalGuard` | Aggregates payment, documents, and appointments |
| `AppointmentBooker` | Row-lock, capacity re-check, insert, increment |
| `FeeResolver` | Dated-rule precedence with ambiguity detection |

**This list is closed.** Any seventh service is a signal that logic is leaking out of models and should be challenged in review. Everything else — status transitions, section status, tracking-number generation, requirement resolution — lives as methods on the relevant model or a query scope.

### 9.3 `CLAUDE.md` amendments

The session primer in the implementation prompts must be revised. Replace the ARCHITECTURE block with:

```
ARCHITECTURE (strict MVC)
- Controllers are thin: validate via Form Request, call a model method or
  one of the six approved services, return a response
- Business logic lives on Eloquent models as methods and scopes
- app/Services/ contains exactly six classes (see tech-stack.md §9.2).
  Do not add a seventh without asking.
- Livewire components are view controllers: they hold UI state and call
  models or services. No business logic in Livewire components.
- Policies on every sensitive model. Every controller method authorises.
- No app/Domain/ directory. No Action classes.
```

---

## 10. Environment Configuration

### 10.1 Shared

```env
APP_NAME="Visa Application System"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=UTC

DB_CONNECTION=mysql
DB_HOST=<managed-mysql-host>
DB_PORT=3306
DB_DATABASE=vas_production
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

REDIS_CLIENT=predis
REDIS_HOST=<managed-redis-host>
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

FILESYSTEM_DISK=s3
AWS_BUCKET=<private-bucket>
AWS_USE_PATH_STYLE_ENDPOINT=false

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

MAIL_MAILER=smtp
SENTRY_LARAVEL_DSN=
```

### 10.2 Vercel-only

```env
LOG_CHANNEL=stderr
APP_CONFIG_CACHE=/tmp/config.php
APP_EVENTS_CACHE=/tmp/events.php
APP_PACKAGES_CACHE=/tmp/packages.php
APP_ROUTES_CACHE=/tmp/routes.php
APP_SERVICES_CACHE=/tmp/services.php
VIEW_COMPILED_PATH=/tmp
```

Also required in `bootstrap/app.php` for correct HTTPS and client IP behind Vercel's proxy — without it, rate limiting keys on the proxy IP and signed URLs break:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*');
})
```

### 10.3 Worker-only

```env
LOG_CHANNEL=daily
HORIZON_PREFIX=vas_horizon:
CLAMAV_HOST=127.0.0.1
CLAMAV_PORT=3310
BACKUP_DISK=s3-backups
```

---

## 11. Development Environment

| Tool | Version | Status |
|---|---|---|
| Docker Engine | 27.x | ⚠ |
| Docker Compose | 2.29.x | ⚠ |
| MySQL image | `mysql:8.0` | ✅ Match production exactly |
| Redis image | `redis:7.2-alpine` | ⚠ |
| MailHog / Mailpit | `axllent/mailpit:latest` | ⚠ |
| MinIO | `minio/minio:latest` | ⚠ S3-compatible local storage |
| ClamAV | `clamav/clamav:1.3` | ⚠ Virus scanning parity |

Local development runs the **worker-host topology**, not the Vercel topology. Serverless constraints are verified in a Vercel preview deployment, not locally — attempting to reproduce them in Docker wastes more time than it saves.

---

## 12. Version Lock Enforcement

### 12.1 Resolution pass — run before the first commit

```bash
# 1. Install with the constraints in §4.1
composer install
npm install

# 2. Capture exact resolved versions
composer show --format=json > docs/resolved-versions.json
composer show --direct | awk '{print $1" "$2}' > docs/locked-versions.txt

# 3. Commit both lockfiles AND the manifests
git add composer.lock package-lock.json docs/locked-versions.txt

# 4. Replace every ⚠ in §4.1 with the exact resolved version
```

After step 4, `composer.json` should contain no wildcards — every constraint an exact version.

### 12.2 CI drift check

```bash
#!/usr/bin/env bash
# .github/scripts/check-version-drift.sh
set -euo pipefail

composer show --direct | awk '{print $1" "$2}' > /tmp/current-versions.txt

if ! diff -u docs/locked-versions.txt /tmp/current-versions.txt; then
  echo "❌ Dependency versions drifted from the locked manifest."
  echo "   Update docs/locked-versions.txt deliberately, or revert."
  exit 1
fi

php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") && version_compare(PHP_VERSION, "8.4.0", "<") ? 0 : 1);' \
  || { echo "❌ PHP is not 8.3.x"; exit 1; }

grep -q 'vercel-php@0.7.4' vercel.json \
  || { echo "❌ vercel.json runtime is not the PHP 8.3 build"; exit 1; }

echo "✅ Versions match the lock."
```

The `vercel-php` check matters more than it looks. It is a string in a JSON file with no dependency resolver behind it — nothing but this test stops someone bumping it to `0.9.0` and silently moving the whole application to PHP 8.5.

### 12.3 Update policy

| Change | Requires |
|---|---|
| Patch (security) | Apply promptly; update `locked-versions.txt` |
| Patch (routine) | Batch monthly; full suite must pass |
| Minor | Deliberate decision; regression pass |
| Major | Written decision record; never mid-milestone |
| `vercel-php` | Only alongside a deliberate PHP version change |
| Laravel 12 → 13 | Post-launch, with the Pest 5 upgrade planned together |

---

## 13. Risk Register

| # | Risk | Severity | Mitigation |
|---|---|---|---|
| R-1 | `vercel-php` is community-maintained with no SLA, no published GitHub releases, and a single primary maintainer. A Vercel platform change could break the HTTP tier with no vendor recourse. | **High** | Keep the worker host provisioned to serve HTTP as a fallback; the same codebase runs there unmodified. Document the cutover in a runbook. Rehearse it once. |
| R-2 | Code skew between Vercel and the worker host — a job serialised by one tier failing to deserialise on the other | **High** | Single pipeline deploying both from the same commit; deploy gate blocks if SHAs diverge |
| R-3 | Connection exhaustion — serverless invocations open MySQL/Redis connections without pooling | **Medium** | Managed services with connection limits; monitor; consider a proxy if p95 degrades |
| R-4 | Cold starts pushing p95 past the 500 ms PRD budget | **Medium** | Single region co-located with the datastores; measure against the budget during the pilot |
| R-5 | Image processing deferred to the worker means a document can be accepted at upload and rejected asynchronously | **Medium** | The App Flow already has a `scanning` state; extend its copy to cover deferred image validation |
| R-6 | Filament removal expands M1.5, M5.1, M5.2, and all ADM screens by roughly 1.5–2× | **Medium** | Reflect in the schedule now, not at M5 |
| R-7 | Strict MVC drifts toward fat controllers as the domain grows | **Medium** | The six-service cap in §9.2 is enforced in review; a seventh service triggers an architecture conversation |
| R-8 | Two hosting bills, two monitoring surfaces, two on-call paths | **Low** | Accepted cost of D-2 |
| R-9 | Laravel 12 and Livewire 3 both one major behind; PHP 8.3 blocks Pest 5 | **Low** | Supported through 2026–27; plan the joint upgrade post-launch |

**On R-1.** This is the one I would keep visible on a wall. The single-region worker host already exists and can serve HTTP with no code change — the fallback is cheap to maintain and worth rehearsing before the pilot rather than during an incident.

---

## 14. What Changed in the Earlier Documents

| Document | Sections needing amendment |
|---|---|
| **PRD** | §OD-1 resolved (Laravel 12). §OD-2 unchanged — integer minor units still recommended and assumed here. Database references PostgreSQL → MySQL 8. |
| **App Flow** | §2.1 host table: officer/admin panels are Livewire, not Filament. Document state machine gains a *deferred image validation* substate. |
| **Screen UI Specs** | §10 OFF-05 and §11 ADM-09 rewritten for Livewire + `laravel-livewire-tables` rather than Filament tabs and resources. Guard-parity rule (§13.1) unchanged and still binding. |
| **Implementation Prompts** | Session primer ARCHITECTURE block replaced (§9.3). M0.4 becomes "Livewire panel scaffolding + CI". M1.5 becomes "Livewire admin CRUD". M2.1 partial-index instruction replaced with the generated-column pattern (§7.3). Every "Filament action authorisation" test retargets to controller methods. Pest pinned to 4.x. |

---

## 15. Open Items

| ID | Item | Blocks |
|---|---|---|
| TS-1 | Run the §12.1 resolution pass and replace every ⚠ with an exact version | First commit |
| TS-2 | Confirm PRD OD-2 — integer minor units. This document assumes yes throughout. | M0.2 |
| TS-3 | Confirm Vercel plan tier — `maxDuration: 60` requires Pro | Deployment |
| TS-4 | Choose worker host provider and region; must co-locate with MySQL and Redis | M0.3 |
| TS-5 | Confirm the virus-scanning approach — self-hosted ClamAV on the worker, or an external API | M3.3 |
| TS-6 | Confirm font licensing per UI-7 | Design tokens |
| TS-7 | Decide whether R-1's HTTP fallback is rehearsed before the pilot | Pre-launch |
