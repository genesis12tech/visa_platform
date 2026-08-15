# Backend Schema Design
## Visa Application System — MySQL 8.0

| Field | Value |
|---|---|
| **Version** | 1.0 |
| **Date** | 13 August 2026 |
| **Database** | MySQL 8.0.x · InnoDB · `utf8mb4` / `utf8mb4_0900_ai_ci` |
| **Application** | Laravel 12, PHP 8.3, strict MVC |
| **Tables** | 53 (41 domain · 12 infrastructure) |
| **Companion documents** | PRD v1.0 · App Flow v1.0 · Screen UI Specs v1.0 · Tech Stack v1.0 · Content Guidelines v1.0 |

---

## 1. Decisions Record

### D-1 · Money: integer minor units — **PRD OD-2 resolved**

```sql
amount_minor  BIGINT NOT NULL,          -- 16000 = $160.00
currency      CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
```

`DECIMAL(12,2)` is rejected for three reasons that matter in this specific system.

1. **Zero-decimal currencies break it.** ¥12,400 JPY has no minor unit. `DECIMAL(12,2)` silently stores `12400.00` and every display path must then special-case the currency. Minor units store `12400` with an exponent of 0 and the formatter handles it once.
2. **Stripe's API is denominated in minor units.** Storing decimals means converting on every write and every webhook read — a conversion that is exactly where rounding defects appear, and this system reconciles daily against the gateway.
3. **Split arithmetic must be exact.** Allocating a refund proportionally across four `payment_items` must distribute remainder pennies deterministically with none lost. Integers plus bcmath do this; decimals invite drift.

Signed `BIGINT` — negatives are needed for refund and reversal ledger lines. A `currencies` reference table carries `minor_unit_exponent` so formatting is data-driven, not hard-coded.

### D-2 · Primary keys: `BIGINT UNSIGNED` internal + `ULID` public — **reverses earlier documents**

Earlier documents specified ULID primary keys with `foreignUlid()` throughout. That guidance came from a PostgreSQL-oriented blueprint. **In MySQL 8 it is the wrong choice**, and the reversal is deliberate.

InnoDB clusters every table on its primary key, and **every secondary index stores a full copy of that primary key** as its row pointer. With `CHAR(26)` PKs across ~40 tables:

- `visa_applications` has 5 secondary indexes → 5 × 26 bytes of PK copy per row, versus 5 × 8 with `BIGINT`
- Foreign keys become 26-byte columns, so joins compare 26 bytes rather than 8
- ULIDs are lexically sortable, so inserts are roughly sequential — but they still cost 3.25× the width
- At the PRD §10.2 target of 250,000 applications/year with a 1,000,000 headroom, secondary index size and buffer-pool pressure both matter

The adopted pattern:

```sql
id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,   -- internal only
ulid    CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
UNIQUE KEY uq_<table>_ulid (ulid)
```

**PRD FR-AP-12 ("raw database identifiers never appear in URLs, emails, or PDFs") is fully satisfied**, because the ULID — or, for applications, the tracking number — is what routes. In Laravel:

```php
public function getRouteKeyName(): string { return 'ulid'; }
// VisaApplication overrides to 'tracking_number'
```

`CHARACTER SET ascii COLLATE ascii_bin` on every ULID column: 26 bytes rather than 104 under `utf8mb4`, with correct binary comparison.

**Reference tables get no ULID at all** — `countries`, `currencies`, `permissions`, and the metrics tables are never addressed publicly.

### D-3 · Integrity: triggers **and** constraints

As chosen. The database enforces what must be true regardless of which code path writes. §7–§9 specify 19 triggers, 24 `CHECK` constraints, and 6 generated columns.

The boundary is deliberate: **the database enforces invariants; the application enforces workflow.** Append-only-ness, non-negative money, capacity ceilings, and separation of duties are invariants — they belong in the schema, because a bug, a console command, or a future service must not be able to violate them. The status transition table is workflow and stays in the application, where its error messages can be useful.

### D-4 · Amendments required in earlier documents

| Document | Change |
|---|---|
| Implementation Prompts M1.1, M2.2, M3.2, M4.1, M6.1, M7.1 | `$table->ulid('id')->primary()` → `$table->id()` plus `$table->char('ulid', 26)->unique()`; `foreignUlid()` → `foreignId()` |
| Tech Stack §7.4 | "ULID columns `CHAR(26)` with `utf8mb4_bin`" → `ascii_bin`, and ULID is no longer the PK |
| PRD §5.2, §OD-2 | OD-2 resolved as integer minor units; PK strategy amended per D-2 |

---

## 2. Conventions

| Concern | Rule |
|---|---|
| Engine | `InnoDB` on every table |
| Charset / collation | `utf8mb4` / `utf8mb4_0900_ai_ci`; ULID, currency, and hash columns `ascii` / `ascii_bin` |
| Table names | `snake_case`, plural |
| PK | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| Public key | `ulid CHAR(26)` on externally addressable tables |
| FK naming | `fk_<table>_<column>` |
| Index naming | `idx_<table>_<cols>`, `uq_<table>_<cols>` |
| Timestamps | `created_at`, `updated_at` `TIMESTAMP NULL`; append-only tables carry `created_at` only |
| Soft deletes | `deleted_at TIMESTAMP NULL` — **only** on `visa_applications` and `users` |
| Booleans | `TINYINT(1) NOT NULL DEFAULT 0` |
| Enums | `VARCHAR` + `CHECK` constraint, never MySQL `ENUM` (altering `ENUM` rewrites the table) |
| JSON | `JSON`; frequently queried paths get a stored generated column plus index |
| Money | `*_minor BIGINT` + `currency CHAR(3)`, always paired |
| Timezone | All `TIMESTAMP` in UTC; display timezone resolved per location |
| Transaction isolation | `READ-COMMITTED` (see §14.1) |

### 2.1 Why `VARCHAR + CHECK` instead of `ENUM`

Adding a status to a MySQL `ENUM` is an `ALTER TABLE` that rewrites the whole table — on `visa_applications` at a million rows, that is a maintenance window. A `VARCHAR(32)` with a `CHECK` constraint gets the same validation, and changing the allowed set is a fast metadata-only constraint swap. The PHP enum remains the source of truth for the application.

---

## 3. Entity Relationship Overview

```
                          ┌──────────────┐
                          │    users     │
                          └──┬────┬───┬──┘
        ┌────────────────────┘    │   └──────────────────┐
        ▼                         ▼                      ▼
┌───────────────────┐    ┌────────────────┐    ┌──────────────────┐
│ applicant_profiles│    │  agency_users  │    │ user_mfa_methods │
└─────────┬─────────┘    └────────┬───────┘    └──────────────────┘
          │                       ▼
          │              ┌────────────────┐
          │              │    agencies    │
          │              └────────┬───────┘
          │                       ▼
          │           ┌───────────────────────┐
          │           │ agent_applicant_links │
          │           └───────────────────────┘
          ▼
┌═══════════════════════════════════════════════════════════════┐
║                     visa_applications                          ║  ◀ hub entity
╚═══╤═════╤═════╤═════╤═════╤═════╤═════╤═════╤═════╤═══════════╝
    │     │     │     │     │     │     │     │     │
    ▼     ▼     ▼     ▼     ▼     ▼     ▼     ▼     ▼
 answers snap  status  docs  pay  appts  notes info_ audit
        shots  hist          │           requests
                            ▼
                    ┌───────────────┐
              payment_items · invoices · refunds
                    payment_webhook_events

REFERENCE (no ULID, never public):
countries · currencies · visa_types · visa_fees · document_types
visa_type_document_requirements · service_locations · holidays
form_templates · rejection_reasons · appointment_slots

CROSS-CUTTING:
audit_logs · export_logs · tracking_lookup_attempts · notifications
daily_application_metrics · daily_payment_metrics
officer_performance_metrics · document_rejection_metrics
```

---

## 4. Table Catalogue

### 4.1 Identity and authentication

```sql
-- ─────────────────────────────────────────────────────────────
CREATE TABLE users (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name                VARCHAR(191) NOT NULL,
  email               VARCHAR(191) NOT NULL,
  email_verified_at   TIMESTAMP NULL,
  password            VARCHAR(255) NOT NULL,
  phone               VARCHAR(32) NULL,
  status              VARCHAR(16) NOT NULL DEFAULT 'pending',
  user_type           VARCHAR(16) NOT NULL DEFAULT 'applicant',
  locale              VARCHAR(10) NOT NULL DEFAULT 'en',
  last_login_at       TIMESTAMP NULL,
  last_login_ip       VARBINARY(16) NULL,
  mfa_enabled_at      TIMESTAMP NULL,
  suspended_at        TIMESTAMP NULL,
  suspended_by_user_id BIGINT UNSIGNED NULL,
  suspension_reason   TEXT NULL,
  remember_token      VARCHAR(100) NULL,
  created_at          TIMESTAMP NULL,
  updated_at          TIMESTAMP NULL,
  deleted_at          TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_ulid  (ulid),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status_type (status, user_type),
  KEY idx_users_last_login  (last_login_at),
  CONSTRAINT fk_users_suspended_by FOREIGN KEY (suspended_by_user_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_users_status
    CHECK (status IN ('pending','active','suspended')),
  CONSTRAINT chk_users_type
    CHECK (user_type IN ('applicant','agent','staff')),
  CONSTRAINT chk_users_suspension
    CHECK ((status <> 'suspended') OR (suspended_at IS NOT NULL AND suspension_reason IS NOT NULL))
) ENGINE=InnoDB;
```

`user_type` separates the three authentication guards (§11). It is coarse; fine-grained capability lives in roles. `last_login_ip` is `VARBINARY(16)` to hold IPv6 via `INET6_ATON`.

```sql
-- ─────────────────────────────────────────────────────────────
CREATE TABLE applicant_profiles (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                     CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id                  BIGINT UNSIGNED NOT NULL,
  first_name               VARCHAR(100) NOT NULL,
  middle_name              VARCHAR(100) NULL,
  last_name                VARCHAR(100) NOT NULL,
  date_of_birth            DATE NOT NULL,
  place_of_birth           VARCHAR(191) NULL,
  sex                      VARCHAR(20) NULL,
  nationality_country_id   BIGINT UNSIGNED NOT NULL,
  passport_number_encrypted TEXT NULL,          -- Laravel encrypted cast
  passport_number_hash     CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  passport_issued_at       DATE NULL,
  passport_expires_at      DATE NULL,
  passport_issuing_country_id BIGINT UNSIGNED NULL,
  address_line1            VARCHAR(191) NULL,
  address_line2            VARCHAR(191) NULL,
  city                     VARCHAR(100) NULL,
  postal_code              VARCHAR(32) NULL,
  residence_country_id     BIGINT UNSIGNED NULL,
  metadata                 JSON NULL,
  created_at               TIMESTAMP NULL,
  updated_at               TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profiles_ulid (ulid),
  UNIQUE KEY uq_profiles_user (user_id),
  KEY idx_profiles_passport_hash (passport_number_hash),
  KEY idx_profiles_nationality   (nationality_country_id),
  KEY idx_profiles_dob           (date_of_birth),
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_profiles_nationality FOREIGN KEY (nationality_country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT fk_profiles_passport_country FOREIGN KEY (passport_issuing_country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT fk_profiles_residence FOREIGN KEY (residence_country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT chk_profiles_passport_dates
    CHECK (passport_issued_at IS NULL OR passport_expires_at IS NULL
           OR passport_expires_at > passport_issued_at)
) ENGINE=InnoDB;
```

**`passport_number_hash` is a blind index.** The encrypted column cannot be searched — Laravel's encryption is non-deterministic, so the same passport number produces different ciphertext each time. Duplicate-detection and fraud checks need a lookup path, so a peppered SHA-256 (`hash_hmac('sha256', $normalised, config('app.blind_index_key'))`) is stored alongside. The pepper lives in the secret store, never in the database, so a database-only compromise does not permit a rainbow attack.

Names are `VARCHAR(100)` and never reformatted (Content Guidelines §6.4). `sex` is `VARCHAR(20)` rather than a constrained set — passports carry values beyond a binary, and rejecting a valid passport value at the database level is a defect.

```sql
-- ─────────────────────────────────────────────────────────────
CREATE TABLE user_mfa_methods (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            BIGINT UNSIGNED NOT NULL,
  type               VARCHAR(16) NOT NULL DEFAULT 'totp',
  secret_encrypted   TEXT NOT NULL,
  confirmed_at       TIMESTAMP NULL,
  last_used_at       TIMESTAMP NULL,
  created_at         TIMESTAMP NULL,
  updated_at         TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mfa_user_type (user_id, type),
  CONSTRAINT fk_mfa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT chk_mfa_type CHECK (type IN ('totp'))
) ENGINE=InnoDB;

CREATE TABLE mfa_recovery_codes (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  code_hash   VARCHAR(255) NOT NULL,          -- bcrypt, never plaintext
  used_at     TIMESTAMP NULL,
  created_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_recovery_user_unused (user_id, used_at),
  CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(191) NOT NULL,
  user_id       BIGINT UNSIGNED NULL,          -- NULL when the email is unknown
  successful    TINYINT(1) NOT NULL DEFAULT 0,
  failure_reason VARCHAR(32) NULL,
  ip_address    VARBINARY(16) NULL,
  user_agent    VARCHAR(500) NULL,
  guard         VARCHAR(16) NOT NULL DEFAULT 'web',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_email_time (email, created_at),
  KEY idx_login_ip_time    (ip_address, created_at),
  KEY idx_login_user_time  (user_id, created_at),
  CONSTRAINT fk_login_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_login_failure CHECK (
    (successful = 1 AND failure_reason IS NULL) OR
    (successful = 0 AND failure_reason IN
      ('bad_credentials','unverified','suspended','mfa_failed','throttled'))
  )
) ENGINE=InnoDB;
```

`login_attempts` records unknown emails with a `NULL` `user_id`. This is what makes credential-stuffing detection possible — an attacker probing addresses that do not exist is the clearest signal available, and it is invisible if only known users are logged.

```sql
CREATE TABLE password_reset_tokens (
  email      VARCHAR(191) NOT NULL,
  token      VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB;

CREATE TABLE sessions (
  id            VARCHAR(191) NOT NULL,
  user_id       BIGINT UNSIGNED NULL,
  ip_address    VARCHAR(45) NULL,
  user_agent    TEXT NULL,
  payload       LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB;
```

> Sessions live in Redis in production (Tech Stack §5.4 — the serverless tier has no persistent filesystem). The table is retained for local development and for the "revoke my other sessions" feature when the Redis driver is swapped.

### 4.2 Authorisation — Spatie tables

```sql
CREATE TABLE permissions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(125) NOT NULL,
  guard_name VARCHAR(125) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions (name, guard_name)
) ENGINE=InnoDB;

CREATE TABLE roles (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(125) NOT NULL,
  guard_name VARCHAR(125) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles (name, guard_name)
) ENGINE=InnoDB;

CREATE TABLE model_has_permissions (
  permission_id BIGINT UNSIGNED NOT NULL,
  model_type    VARCHAR(125) NOT NULL,
  model_id      BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, model_id, model_type),
  KEY idx_mhp_model (model_id, model_type),
  CONSTRAINT fk_mhp_permission FOREIGN KEY (permission_id)
    REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE model_has_roles (
  role_id    BIGINT UNSIGNED NOT NULL,
  model_type VARCHAR(125) NOT NULL,
  model_id   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, model_id, model_type),
  KEY idx_mhr_model (model_id, model_type),
  CONSTRAINT fk_mhr_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE role_has_permissions (
  permission_id BIGINT UNSIGNED NOT NULL,
  role_id       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, role_id),
  CONSTRAINT fk_rhp_permission FOREIGN KEY (permission_id)
    REFERENCES permissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_rhp_role FOREIGN KEY (role_id)
    REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### 4.3 Reference data

```sql
CREATE TABLE currencies (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code                CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name                VARCHAR(100) NOT NULL,
  symbol              VARCHAR(8) NOT NULL,
  minor_unit_exponent TINYINT UNSIGNED NOT NULL DEFAULT 2,   -- JPY = 0
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_currencies_code (code),
  CONSTRAINT chk_currencies_exponent CHECK (minor_unit_exponent <= 4)
) ENGINE=InnoDB;

CREATE TABLE countries (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  iso2      CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  iso3      CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name      VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_countries_iso2 (iso2),
  UNIQUE KEY uq_countries_iso3 (iso3),
  KEY idx_countries_active_name (is_active, name)
) ENGINE=InnoDB;

CREATE TABLE visa_types (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                 CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  country_id           BIGINT UNSIGNED NOT NULL,
  code                 VARCHAR(32) NOT NULL,
  name                 VARCHAR(100) NOT NULL,
  description          TEXT NULL,
  processing_days_min  SMALLINT UNSIGNED NOT NULL,
  processing_days_max  SMALLINT UNSIGNED NOT NULL,
  sla_business_hours   SMALLINT UNSIGNED NOT NULL DEFAULT 240,
  requires_biometrics  TINYINT(1) NOT NULL DEFAULT 0,
  requires_interview   TINYINT(1) NOT NULL DEFAULT 0,
  requires_four_eyes   TINYINT(1) NOT NULL DEFAULT 0,
  max_reschedules      TINYINT UNSIGNED NOT NULL DEFAULT 2,
  is_active            TINYINT(1) NOT NULL DEFAULT 0,
  created_at           TIMESTAMP NULL,
  updated_at           TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_visa_types_ulid (ulid),
  UNIQUE KEY uq_visa_types_country_code (country_id, code),
  KEY idx_visa_types_active (is_active, country_id),
  CONSTRAINT fk_visa_types_country FOREIGN KEY (country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT chk_visa_types_processing
    CHECK (processing_days_max >= processing_days_min)
) ENGINE=InnoDB;

CREATE TABLE visa_fees (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                   CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  visa_type_id           BIGINT UNSIGNED NOT NULL,
  nationality_country_id BIGINT UNSIGNED NULL,     -- NULL = applies to all
  currency               CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  base_fee_minor         BIGINT NOT NULL,
  service_fee_minor      BIGINT NOT NULL DEFAULT 0,
  priority_fee_minor     BIGINT NOT NULL DEFAULT 0,
  tax_minor              BIGINT NOT NULL DEFAULT 0,
  valid_from             DATE NOT NULL,
  valid_until            DATE NULL,
  is_active              TINYINT(1) NOT NULL DEFAULT 1,
  created_at             TIMESTAMP NULL,
  updated_at             TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_visa_fees_ulid (ulid),
  KEY idx_fees_resolution (visa_type_id, nationality_country_id, is_active, valid_from, valid_until),
  CONSTRAINT fk_fees_visa_type FOREIGN KEY (visa_type_id)
    REFERENCES visa_types (id) ON DELETE RESTRICT,
  CONSTRAINT fk_fees_nationality FOREIGN KEY (nationality_country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT chk_fees_nonneg CHECK (
    base_fee_minor >= 0 AND service_fee_minor >= 0
    AND priority_fee_minor >= 0 AND tax_minor >= 0
  ),
  CONSTRAINT chk_fees_window CHECK (valid_until IS NULL OR valid_until > valid_from)
) ENGINE=InnoDB;
```

`idx_fees_resolution` is ordered to serve the exact `ResolveVisaFee` predicate: visa type, then nationality (specific rows before the `NULL` catch-all), then active flag, then the date window.

```sql
CREATE TABLE document_types (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid               CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code               VARCHAR(64) NOT NULL,
  name               VARCHAR(100) NOT NULL,
  description        TEXT NULL,
  allowed_mime_types JSON NOT NULL,
  allowed_extensions JSON NOT NULL,
  max_size_bytes     BIGINT UNSIGNED NOT NULL DEFAULT 10485760,
  max_pages          SMALLINT UNSIGNED NULL,
  min_width_px       SMALLINT UNSIGNED NULL,
  min_height_px      SMALLINT UNSIGNED NULL,
  is_active          TINYINT(1) NOT NULL DEFAULT 1,
  created_at         TIMESTAMP NULL,
  updated_at         TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_doc_types_ulid (ulid),
  UNIQUE KEY uq_doc_types_code (code),
  CONSTRAINT chk_doc_types_size CHECK (max_size_bytes BETWEEN 1024 AND 52428800)
) ENGINE=InnoDB;

CREATE TABLE visa_type_document_requirements (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visa_type_id     BIGINT UNSIGNED NOT NULL,
  document_type_id BIGINT UNSIGNED NOT NULL,
  is_required      TINYINT(1) NOT NULL DEFAULT 1,
  condition_rules  JSON NULL,
  sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vtdr (visa_type_id, document_type_id),
  KEY idx_vtdr_order (visa_type_id, sort_order),
  CONSTRAINT fk_vtdr_visa_type FOREIGN KEY (visa_type_id)
    REFERENCES visa_types (id) ON DELETE CASCADE,
  CONSTRAINT fk_vtdr_doc_type FOREIGN KEY (document_type_id)
    REFERENCES document_types (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE service_locations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid            CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name            VARCHAR(150) NOT NULL,
  country_id      BIGINT UNSIGNED NOT NULL,
  address_line1   VARCHAR(191) NOT NULL,
  address_line2   VARCHAR(191) NULL,
  city            VARCHAR(100) NOT NULL,
  postal_code     VARCHAR(32) NULL,
  timezone        VARCHAR(64) NOT NULL,        -- IANA, e.g. Asia/Kolkata
  latitude        DECIMAL(10,7) NULL,
  longitude       DECIMAL(10,7) NULL,
  operating_hours JSON NOT NULL,
  contact_phone   VARCHAR(32) NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_locations_ulid (ulid),
  KEY idx_locations_active (is_active, country_id),
  CONSTRAINT fk_locations_country FOREIGN KEY (country_id)
    REFERENCES countries (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE holidays (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_id BIGINT UNSIGNED NULL,   -- NULL = mission-wide, used for SLA
  holiday_date DATE NOT NULL,
  description VARCHAR(150) NOT NULL,
  created_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_holidays (location_id, holiday_date),
  KEY idx_holidays_date (holiday_date),
  CONSTRAINT fk_holidays_location FOREIGN KEY (location_id)
    REFERENCES service_locations (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rejection_reasons (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope      VARCHAR(16) NOT NULL,     -- document | decision
  code       VARCHAR(64) NOT NULL,
  label      VARCHAR(191) NOT NULL,
  applicant_text TEXT NOT NULL,        -- plain-language version shown to applicant
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rejection_scope_code (scope, code),
  CONSTRAINT chk_rejection_scope CHECK (scope IN ('document','decision'))
) ENGINE=InnoDB;
```

`rejection_reasons.applicant_text` exists because the officer's internal category and the applicant's plain-language explanation are different strings, and the Content Guidelines require the latter. Storing both keeps the officer's taxonomy stable while the applicant-facing wording stays translatable.

### 4.4 Forms

```sql
CREATE TABLE form_templates (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid               CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  visa_type_id       BIGINT UNSIGNED NOT NULL,
  version            SMALLINT UNSIGNED NOT NULL,
  name               VARCHAR(150) NOT NULL,
  schema_json        JSON NOT NULL,
  is_active          TINYINT(1) NOT NULL DEFAULT 0,
  published_at       TIMESTAMP NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at         TIMESTAMP NULL,
  updated_at         TIMESTAMP NULL,

  -- Generated column emulating a PostgreSQL partial unique index:
  -- NULL when inactive, so many inactive rows coexist while at most
  -- one active row per visa type is possible. (Tech Stack §7.3)
  active_visa_type_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (IF(is_active = 1, visa_type_id, NULL)) STORED,

  PRIMARY KEY (id),
  UNIQUE KEY uq_templates_ulid (ulid),
  UNIQUE KEY uq_templates_version (visa_type_id, version),
  UNIQUE KEY uq_templates_one_active (active_visa_type_id),
  CONSTRAINT fk_templates_visa_type FOREIGN KEY (visa_type_id)
    REFERENCES visa_types (id) ON DELETE RESTRICT,
  CONSTRAINT fk_templates_creator FOREIGN KEY (created_by_user_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_templates_active_published
    CHECK (is_active = 0 OR published_at IS NOT NULL)
) ENGINE=InnoDB;
```

`schema_json` is deliberately named to avoid `SCHEMA`, a MySQL reserved word.

```sql
CREATE TABLE application_answers (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  section_key    VARCHAR(64) NOT NULL,
  field_key      VARCHAR(64) NOT NULL,
  value_json     JSON NULL,
  is_encrypted   TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_answers (application_id, section_key, field_key),
  KEY idx_answers_section (application_id, section_key),
  CONSTRAINT fk_answers_application FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE application_snapshots (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid             CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_id   BIGINT UNSIGNED NOT NULL,
  form_template_id BIGINT UNSIGNED NOT NULL,
  payload_json     JSON NOT NULL,
  checksum         CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_snapshots_ulid (ulid),
  UNIQUE KEY uq_snapshots_application (application_id),
  CONSTRAINT fk_snapshots_application FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE RESTRICT,
  CONSTRAINT fk_snapshots_template FOREIGN KEY (form_template_id)
    REFERENCES form_templates (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

`ON DELETE RESTRICT` on the snapshot's application FK is intentional: a submitted application with a snapshot cannot be hard-deleted, which is the schema-level guarantee behind PRD BR-01.

### 4.5 Applications — the hub entity

```sql
CREATE TABLE visa_applications (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                   CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tracking_number        VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id                BIGINT UNSIGNED NOT NULL,
  applicant_profile_id   BIGINT UNSIGNED NOT NULL,
  visa_type_id           BIGINT UNSIGNED NOT NULL,
  form_template_id       BIGINT UNSIGNED NOT NULL,
  destination_country_id BIGINT UNSIGNED NOT NULL,
  nationality_country_id BIGINT UNSIGNED NOT NULL,

  status                 VARCHAR(32) NOT NULL DEFAULT 'draft',
  public_status          VARCHAR(32) NOT NULL DEFAULT 'draft',

  fee_total_minor        BIGINT NULL,
  fee_currency           CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL,
  visa_fee_id            BIGINT UNSIGNED NULL,

  filed_by_agent_user_id BIGINT UNSIGNED NULL,
  agency_id              BIGINT UNSIGNED NULL,

  assigned_to_user_id    BIGINT UNSIGNED NULL,
  assigned_at            TIMESTAMP NULL,

  submitted_at           TIMESTAMP NULL,
  paid_at                TIMESTAMP NULL,
  decided_by_user_id     BIGINT UNSIGNED NULL,
  countersigned_by_user_id BIGINT UNSIGNED NULL,
  decided_at             TIMESTAMP NULL,
  decision               VARCHAR(16) NULL,
  decision_reason_id     BIGINT UNSIGNED NULL,
  decision_notes         TEXT NULL,

  sla_due_at             TIMESTAMP NULL,
  sla_state              VARCHAR(16) NOT NULL DEFAULT 'not_started',
  sla_paused_seconds     INT UNSIGNED NOT NULL DEFAULT 0,

  lock_version           INT UNSIGNED NOT NULL DEFAULT 0,
  metadata               JSON NULL,
  created_at             TIMESTAMP NULL,
  updated_at             TIMESTAMP NULL,
  deleted_at             TIMESTAMP NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_apps_ulid (ulid),
  UNIQUE KEY uq_apps_tracking (tracking_number),
  KEY idx_apps_user_status      (user_id, status),
  KEY idx_apps_status_submitted (status, submitted_at),
  KEY idx_apps_assigned_status  (assigned_to_user_id, status),
  KEY idx_apps_sla              (sla_state, sla_due_at),
  KEY idx_apps_type_submitted   (visa_type_id, submitted_at),
  KEY idx_apps_agency           (agency_id, status),
  KEY idx_apps_reporting        (submitted_at, destination_country_id, visa_type_id),

  CONSTRAINT fk_apps_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_profile FOREIGN KEY (applicant_profile_id)
    REFERENCES applicant_profiles (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_visa_type FOREIGN KEY (visa_type_id)
    REFERENCES visa_types (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_template FOREIGN KEY (form_template_id)
    REFERENCES form_templates (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_destination FOREIGN KEY (destination_country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_nationality FOREIGN KEY (nationality_country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_fee FOREIGN KEY (visa_fee_id)
    REFERENCES visa_fees (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_agent FOREIGN KEY (filed_by_agent_user_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_apps_agency FOREIGN KEY (agency_id)
    REFERENCES agencies (id) ON DELETE SET NULL,
  CONSTRAINT fk_apps_assigned FOREIGN KEY (assigned_to_user_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_apps_decided_by FOREIGN KEY (decided_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_countersigned FOREIGN KEY (countersigned_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_apps_decision_reason FOREIGN KEY (decision_reason_id)
    REFERENCES rejection_reasons (id) ON DELETE RESTRICT,

  CONSTRAINT chk_apps_status CHECK (status IN (
    'draft','submitted','payment_pending','paid','under_review','info_requested',
    'resubmitted','document_verification','interview_scheduled','decision_pending',
    'approved','rejected','withdrawn','closed')),
  CONSTRAINT chk_apps_public_status CHECK (public_status IN (
    'draft','submitted','in_review','action_required',
    'appointment_scheduled','decision_made','withdrawn','closed')),
  CONSTRAINT chk_apps_decision CHECK (decision IS NULL OR decision IN ('approved','rejected')),
  CONSTRAINT chk_apps_sla_state CHECK (sla_state IN ('not_started','on_track','at_risk','breached','stopped')),
  CONSTRAINT chk_apps_fee_pair CHECK (
    (fee_total_minor IS NULL AND fee_currency IS NULL) OR
    (fee_total_minor IS NOT NULL AND fee_currency IS NOT NULL AND fee_total_minor >= 0)
  ),
  CONSTRAINT chk_apps_decision_complete CHECK (
    (decision IS NULL AND decided_at IS NULL AND decided_by_user_id IS NULL) OR
    (decision IS NOT NULL AND decided_at IS NOT NULL AND decided_by_user_id IS NOT NULL)
  ),
  -- Four-eyes: the countersigner can never be the deciding officer.
  CONSTRAINT chk_apps_four_eyes CHECK (
    countersigned_by_user_id IS NULL OR countersigned_by_user_id <> decided_by_user_id
  ),
  CONSTRAINT chk_apps_submitted_fee CHECK (
    status = 'draft' OR (submitted_at IS NOT NULL AND fee_total_minor IS NOT NULL)
  ),
  CONSTRAINT chk_apps_agent_pair CHECK (
    (filed_by_agent_user_id IS NULL AND agency_id IS NULL) OR
    (filed_by_agent_user_id IS NOT NULL AND agency_id IS NOT NULL)
  )
) ENGINE=InnoDB;
```

Three constraints are doing real work here. `chk_apps_four_eyes` makes self-countersignature impossible at the storage layer, so no code path can produce it. `chk_apps_decision_complete` prevents a half-written decision — actor, timestamp, and outcome arrive together or not at all, which is what makes PRD FR-OR-09 evidentially sound. `chk_apps_submitted_fee` enforces PRD BR-15: anything past draft has its fee frozen.

`lock_version` supports the optimistic concurrency in Screen UI Specs §10.4.

```sql
CREATE TABLE application_status_histories (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  from_status    VARCHAR(32) NULL,          -- NULL on creation
  to_status      VARCHAR(32) NOT NULL,
  actor_user_id  BIGINT UNSIGNED NULL,      -- NULL = system
  actor_type     VARCHAR(16) NOT NULL DEFAULT 'system',
  reason         TEXT NULL,
  metadata       JSON NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_hist_app (application_id, created_at),
  KEY idx_status_hist_to  (to_status, created_at),
  CONSTRAINT fk_status_hist_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE RESTRICT,
  CONSTRAINT fk_status_hist_actor FOREIGN KEY (actor_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_status_hist_actor CHECK (actor_type IN ('system','applicant','agent','officer','admin')),
  CONSTRAINT chk_status_hist_reason CHECK (
    to_status NOT IN ('rejected','info_requested','withdrawn') OR reason IS NOT NULL
  )
) ENGINE=InnoDB;

CREATE TABLE review_notes (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid           CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  visibility     VARCHAR(16) NOT NULL DEFAULT 'internal',
  note           TEXT NOT NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notes_ulid (ulid),
  KEY idx_notes_app (application_id, created_at),
  CONSTRAINT fk_notes_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE CASCADE,
  CONSTRAINT fk_notes_author FOREIGN KEY (author_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_notes_visibility CHECK (visibility IN ('internal','applicant'))
) ENGINE=InnoDB;

CREATE TABLE information_requests (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                 CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_id       BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  message              TEXT NOT NULL,
  deadline_at          TIMESTAMP NULL,
  resolved_at          TIMESTAMP NULL,
  created_at           TIMESTAMP NULL,
  updated_at           TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inforeq_ulid (ulid),
  KEY idx_inforeq_app (application_id, resolved_at),
  CONSTRAINT fk_inforeq_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE CASCADE,
  CONSTRAINT fk_inforeq_requester FOREIGN KEY (requested_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE information_request_items (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  information_request_id BIGINT UNSIGNED NOT NULL,
  item_type              VARCHAR(16) NOT NULL,
  target_key             VARCHAR(64) NULL,      -- section_key or document_type code
  document_type_id       BIGINT UNSIGNED NULL,
  instruction            TEXT NOT NULL,
  completed_at           TIMESTAMP NULL,
  sort_order             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_inforeq_items (information_request_id, sort_order),
  CONSTRAINT fk_inforeq_items_request FOREIGN KEY (information_request_id)
    REFERENCES information_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_inforeq_items_doctype FOREIGN KEY (document_type_id)
    REFERENCES document_types (id) ON DELETE RESTRICT,
  CONSTRAINT chk_inforeq_item_type CHECK (item_type IN ('section','document','acknowledgement')),
  CONSTRAINT chk_inforeq_item_target CHECK (
    (item_type = 'section'         AND target_key IS NOT NULL) OR
    (item_type = 'document'        AND document_type_id IS NOT NULL) OR
    (item_type = 'acknowledgement')
  )
) ENGINE=InnoDB;
```

### 4.6 Documents

```sql
CREATE TABLE application_documents (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                 CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_id       BIGINT UNSIGNED NOT NULL,
  document_type_id     BIGINT UNSIGNED NOT NULL,
  uploaded_by_user_id  BIGINT UNSIGNED NOT NULL,
  current_version_id   BIGINT UNSIGNED NULL,
  status               VARCHAR(32) NOT NULL DEFAULT 'not_uploaded',
  reviewed_by_user_id  BIGINT UNSIGNED NULL,
  reviewed_at          TIMESTAMP NULL,
  rejection_reason_id  BIGINT UNSIGNED NULL,
  rejection_notes      TEXT NULL,
  created_at           TIMESTAMP NULL,
  updated_at           TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_docs_ulid (ulid),
  UNIQUE KEY uq_docs_app_type (application_id, document_type_id),
  KEY idx_docs_status (status, application_id),
  CONSTRAINT fk_docs_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE CASCADE,
  CONSTRAINT fk_docs_type FOREIGN KEY (document_type_id)
    REFERENCES document_types (id) ON DELETE RESTRICT,
  CONSTRAINT fk_docs_uploader FOREIGN KEY (uploaded_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_docs_reviewer FOREIGN KEY (reviewed_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_docs_reason FOREIGN KEY (rejection_reason_id)
    REFERENCES rejection_reasons (id) ON DELETE RESTRICT,
  CONSTRAINT chk_docs_status CHECK (status IN (
    'not_uploaded','uploading','scanning','uploaded','under_review',
    'accepted','rejected','resubmission_required','scan_failed')),
  CONSTRAINT chk_docs_rejection CHECK (
    status NOT IN ('rejected','resubmission_required') OR rejection_reason_id IS NOT NULL
  ),
  CONSTRAINT chk_docs_review CHECK (
    (reviewed_at IS NULL AND reviewed_by_user_id IS NULL) OR
    (reviewed_at IS NOT NULL AND reviewed_by_user_id IS NOT NULL)
  )
) ENGINE=InnoDB;

CREATE TABLE document_versions (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                    CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_document_id BIGINT UNSIGNED NOT NULL,
  version_number          SMALLINT UNSIGNED NOT NULL,
  storage_disk            VARCHAR(32) NOT NULL DEFAULT 's3',
  storage_path            VARCHAR(500) NOT NULL,
  original_filename       VARCHAR(255) NOT NULL,
  mime_type               VARCHAR(127) NOT NULL,
  file_size_bytes         BIGINT UNSIGNED NOT NULL,
  page_count              SMALLINT UNSIGNED NULL,
  width_px                SMALLINT UNSIGNED NULL,
  height_px               SMALLINT UNSIGNED NULL,
  sha256_checksum         CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  virus_scan_status       VARCHAR(16) NOT NULL DEFAULT 'pending',
  virus_scan_at           TIMESTAMP NULL,
  virus_scan_detail       VARCHAR(255) NULL,
  image_check_status      VARCHAR(16) NOT NULL DEFAULT 'pending',
  uploaded_by_user_id     BIGINT UNSIGNED NOT NULL,
  created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_versions_ulid (ulid),
  UNIQUE KEY uq_versions_number (application_document_id, version_number),
  UNIQUE KEY uq_versions_path (storage_path),
  KEY idx_versions_checksum (sha256_checksum),
  KEY idx_versions_scan (virus_scan_status, created_at),
  CONSTRAINT fk_versions_doc FOREIGN KEY (application_document_id)
    REFERENCES application_documents (id) ON DELETE RESTRICT,
  CONSTRAINT fk_versions_uploader FOREIGN KEY (uploaded_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_versions_scan CHECK (virus_scan_status IN ('pending','clean','infected','failed')),
  CONSTRAINT chk_versions_image CHECK (image_check_status IN ('pending','passed','failed','not_applicable')),
  CONSTRAINT chk_versions_size CHECK (file_size_bytes > 0),
  CONSTRAINT chk_versions_number CHECK (version_number >= 1)
) ENGINE=InnoDB;

ALTER TABLE application_documents
  ADD CONSTRAINT fk_docs_current_version FOREIGN KEY (current_version_id)
    REFERENCES document_versions (id) ON DELETE SET NULL;
```

`image_check_status` exists because of the Vercel constraint in Tech Stack §5.3 — the serverless tier has no `gd` or `imagick`, so dimension validation is deferred to a worker job. The column makes that deferred outcome first-class rather than implicit.

### 4.7 Payments

```sql
CREATE TABLE payments (
  id                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                         CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_id               BIGINT UNSIGNED NOT NULL,
  user_id                      BIGINT UNSIGNED NOT NULL,
  paid_by_agent_user_id        BIGINT UNSIGNED NULL,
  provider                     VARCHAR(32) NOT NULL DEFAULT 'stripe',
  provider_payment_id          VARCHAR(191) NULL,
  provider_checkout_session_id VARCHAR(191) NULL,
  idempotency_key              VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  currency                     CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  amount_minor                 BIGINT NOT NULL,
  refunded_amount_minor        BIGINT NOT NULL DEFAULT 0,
  status                       VARCHAR(24) NOT NULL DEFAULT 'pending',
  failure_code                 VARCHAR(64) NULL,
  failure_reason               VARCHAR(500) NULL,
  paid_at                      TIMESTAMP NULL,
  created_at                   TIMESTAMP NULL,
  updated_at                   TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_ulid (ulid),
  UNIQUE KEY uq_payments_provider_id (provider, provider_payment_id),
  UNIQUE KEY uq_payments_idempotency (idempotency_key),
  KEY idx_payments_app (application_id, status),
  KEY idx_payments_recon (status, paid_at, currency),
  KEY idx_payments_session (provider_checkout_session_id),
  CONSTRAINT fk_payments_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_agent FOREIGN KEY (paid_by_agent_user_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_payments_status CHECK (status IN
    ('pending','succeeded','failed','refunded','partially_refunded','cancelled')),
  CONSTRAINT chk_payments_amount CHECK (amount_minor > 0),
  CONSTRAINT chk_payments_refund_bound CHECK (
    refunded_amount_minor >= 0 AND refunded_amount_minor <= amount_minor
  ),
  CONSTRAINT chk_payments_succeeded CHECK (
    status <> 'succeeded' OR (paid_at IS NOT NULL AND provider_payment_id IS NOT NULL)
  )
) ENGINE=InnoDB;
```

`chk_payments_refund_bound` makes over-refunding structurally impossible — a refund exceeding the original payment cannot be persisted even by a bug in the refund service.

```sql
CREATE TABLE payment_items (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id    BIGINT UNSIGNED NOT NULL,
  item_type     VARCHAR(24) NOT NULL,
  description   VARCHAR(191) NOT NULL,
  amount_minor  BIGINT NOT NULL,
  sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_payment_items (payment_id, sort_order),
  CONSTRAINT fk_payment_items_payment FOREIGN KEY (payment_id)
    REFERENCES payments (id) ON DELETE CASCADE,
  CONSTRAINT chk_payment_item_type CHECK (item_type IN
    ('visa_fee','service_fee','priority_fee','tax')),
  CONSTRAINT chk_payment_item_amount CHECK (amount_minor >= 0)
) ENGINE=InnoDB;

CREATE TABLE payment_webhook_events (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider          VARCHAR(32) NOT NULL,
  provider_event_id VARCHAR(191) NOT NULL,
  event_type        VARCHAR(100) NOT NULL,
  payload_json      JSON NOT NULL,
  payment_id        BIGINT UNSIGNED NULL,
  signature_valid   TINYINT(1) NOT NULL DEFAULT 1,
  received_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at      TIMESTAMP NULL,
  processing_error  TEXT NULL,
  attempt_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_webhook_event (provider, provider_event_id),   -- idempotency anchor
  KEY idx_webhook_unprocessed (processed_at, received_at),
  KEY idx_webhook_payment (payment_id),
  CONSTRAINT fk_webhook_payment FOREIGN KEY (payment_id)
    REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB;
```

`uq_webhook_event` is the single most important index in the schema. PRD BR-06 (a webhook is processed exactly once regardless of delivery count) reduces entirely to this unique constraint plus a `firstOrCreate`. Everything else in the webhook path is application logic that this index makes safe.

```sql
CREATE TABLE invoices (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid            CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_id  BIGINT UNSIGNED NOT NULL,
  payment_id      BIGINT UNSIGNED NOT NULL,
  invoice_number  VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  currency        CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  subtotal_minor  BIGINT NOT NULL,
  tax_minor       BIGINT NOT NULL DEFAULT 0,
  total_minor     BIGINT NOT NULL,
  pdf_storage_path VARCHAR(500) NULL,
  pdf_generated_at TIMESTAMP NULL,
  issued_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_ulid (ulid),
  UNIQUE KEY uq_invoices_number (invoice_number),
  UNIQUE KEY uq_invoices_payment (payment_id),
  KEY idx_invoices_app (application_id),
  CONSTRAINT fk_invoices_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoices_payment FOREIGN KEY (payment_id)
    REFERENCES payments (id) ON DELETE RESTRICT,
  CONSTRAINT chk_invoices_total CHECK (total_minor = subtotal_minor + tax_minor),
  CONSTRAINT chk_invoices_nonneg CHECK (subtotal_minor >= 0 AND tax_minor >= 0)
) ENGINE=InnoDB;

CREATE TABLE refunds (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                 CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  payment_id           BIGINT UNSIGNED NOT NULL,
  amount_minor         BIGINT NOT NULL,
  currency             CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  reason               TEXT NOT NULL,
  status               VARCHAR(16) NOT NULL DEFAULT 'requested',
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  requested_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_by_user_id  BIGINT UNSIGNED NULL,
  approved_at          TIMESTAMP NULL,
  declined_reason      TEXT NULL,
  provider_refund_id   VARCHAR(191) NULL,
  completed_at         TIMESTAMP NULL,
  created_at           TIMESTAMP NULL,
  updated_at           TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refunds_ulid (ulid),
  UNIQUE KEY uq_refunds_provider (provider_refund_id),
  KEY idx_refunds_payment (payment_id, status),
  KEY idx_refunds_pending (status, requested_at),
  CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id)
    REFERENCES payments (id) ON DELETE RESTRICT,
  CONSTRAINT fk_refunds_requester FOREIGN KEY (requested_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_refunds_approver FOREIGN KEY (approved_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_refunds_status CHECK (status IN
    ('requested','approved','declined','completed','failed')),
  CONSTRAINT chk_refunds_amount CHECK (amount_minor > 0),
  -- SEPARATION OF DUTIES (PRD BR-13): approver ≠ requester, enforced in storage.
  CONSTRAINT chk_refunds_four_eyes CHECK (
    approved_by_user_id IS NULL OR approved_by_user_id <> requested_by_user_id
  ),
  CONSTRAINT chk_refunds_approval CHECK (
    (status IN ('requested','declined')) OR
    (approved_by_user_id IS NOT NULL AND approved_at IS NOT NULL)
  )
) ENGINE=InnoDB;
```

### 4.8 Appointments

```sql
CREATE TABLE appointment_slots (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_id      BIGINT UNSIGNED NOT NULL,
  appointment_type VARCHAR(24) NOT NULL,
  starts_at        TIMESTAMP NOT NULL,        -- UTC
  ends_at          TIMESTAMP NOT NULL,
  capacity         SMALLINT UNSIGNED NOT NULL,
  booked_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_blocked       TINYINT(1) NOT NULL DEFAULT 0,
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slots (location_id, appointment_type, starts_at),
  KEY idx_slots_availability (location_id, appointment_type, starts_at, is_blocked),
  CONSTRAINT fk_slots_location FOREIGN KEY (location_id)
    REFERENCES service_locations (id) ON DELETE CASCADE,
  CONSTRAINT chk_slots_type CHECK (appointment_type IN
    ('biometrics','interview','document_drop')),
  CONSTRAINT chk_slots_window CHECK (ends_at > starts_at),
  CONSTRAINT chk_slots_capacity CHECK (capacity > 0),
  -- OVERBOOKING IS IMPOSSIBLE (PRD BR-17), regardless of application code.
  CONSTRAINT chk_slots_booked CHECK (booked_count <= capacity)
) ENGINE=InnoDB;
```

`chk_slots_booked` is the belt to the application's braces. The `BookAppointment` action row-locks the slot and re-checks capacity, but if that logic is ever wrong, the constraint rejects the write rather than silently overbooking an applicant who has travelled to attend.

```sql
CREATE TABLE appointments (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid               CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  application_id     BIGINT UNSIGNED NOT NULL,
  slot_id            BIGINT UNSIGNED NOT NULL,
  appointment_type   VARCHAR(24) NOT NULL,
  status             VARCHAR(16) NOT NULL DEFAULT 'scheduled',
  external_reference VARCHAR(100) NULL,      -- biometric enrolment ref; NO biometric data
  reschedule_count   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  booked_by_user_id  BIGINT UNSIGNED NOT NULL,
  completed_at       TIMESTAMP NULL,
  cancelled_at       TIMESTAMP NULL,
  cancellation_reason TEXT NULL,
  letter_storage_path VARCHAR(500) NULL,
  created_at         TIMESTAMP NULL,
  updated_at         TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_appointments_ulid (ulid),
  KEY idx_appointments_app (application_id, status),
  KEY idx_appointments_slot (slot_id),
  KEY idx_appointments_roster (slot_id, status),
  CONSTRAINT fk_appointments_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE RESTRICT,
  CONSTRAINT fk_appointments_slot FOREIGN KEY (slot_id)
    REFERENCES appointment_slots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_appointments_booker FOREIGN KEY (booked_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_appointments_status CHECK (status IN
    ('scheduled','completed','missed','cancelled')),
  CONSTRAINT chk_appointments_completed CHECK (
    status <> 'completed' OR completed_at IS NOT NULL
  )
) ENGINE=InnoDB;
```

> **No biometric data is stored anywhere in this schema.** `external_reference` holds the visa application centre's enrolment identifier only. PRD §2.3 scopes capture out entirely; adding it would change the regulatory profile of the whole system.

### 4.9 Agents

```sql
CREATE TABLE agencies (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name                VARCHAR(191) NOT NULL,
  registration_number VARCHAR(100) NOT NULL,
  country_id          BIGINT UNSIGNED NOT NULL,
  contact_email       VARCHAR(191) NOT NULL,
  contact_phone       VARCHAR(32) NULL,
  status              VARCHAR(16) NOT NULL DEFAULT 'pending',
  vetted_by_user_id   BIGINT UNSIGNED NULL,
  vetted_at           TIMESTAMP NULL,
  rejection_reason    TEXT NULL,
  suspended_at        TIMESTAMP NULL,
  accreditation_expires_at DATE NULL,
  created_at          TIMESTAMP NULL,
  updated_at          TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agencies_ulid (ulid),
  UNIQUE KEY uq_agencies_registration (country_id, registration_number),
  KEY idx_agencies_status (status),
  CONSTRAINT fk_agencies_country FOREIGN KEY (country_id)
    REFERENCES countries (id) ON DELETE RESTRICT,
  CONSTRAINT fk_agencies_vetter FOREIGN KEY (vetted_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_agencies_status CHECK (status IN
    ('pending','active','suspended','rejected')),
  CONSTRAINT chk_agencies_vetted CHECK (
    status IN ('pending') OR (vetted_by_user_id IS NOT NULL AND vetted_at IS NOT NULL)
  )
) ENGINE=InnoDB;

CREATE TABLE agency_users (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agency_id   BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  agency_role VARCHAR(16) NOT NULL DEFAULT 'member',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agency_user (agency_id, user_id),
  KEY idx_agency_users_user (user_id, is_active),
  CONSTRAINT fk_agency_users_agency FOREIGN KEY (agency_id)
    REFERENCES agencies (id) ON DELETE CASCADE,
  CONSTRAINT fk_agency_users_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT chk_agency_role CHECK (agency_role IN ('owner','member'))
) ENGINE=InnoDB;

CREATE TABLE agent_applicant_links (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  agency_id           BIGINT UNSIGNED NOT NULL,
  applicant_user_id   BIGINT UNSIGNED NOT NULL,
  invited_by_user_id  BIGINT UNSIGNED NOT NULL,
  status              VARCHAR(16) NOT NULL DEFAULT 'pending',
  scope_json          JSON NULL,
  consented_at        TIMESTAMP NULL,
  consent_method      VARCHAR(32) NULL,
  consent_ip          VARBINARY(16) NULL,
  expires_at          TIMESTAMP NULL,
  revoked_at          TIMESTAMP NULL,
  revoked_by_user_id  BIGINT UNSIGNED NULL,
  created_at          TIMESTAMP NULL,
  updated_at          TIMESTAMP NULL,

  -- At most ONE active link per (agency, applicant). Revoked and expired
  -- rows are retained as history and coexist freely.
  active_pair_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin
    GENERATED ALWAYS AS (
      IF(status = 'active', SHA2(CONCAT(agency_id, ':', applicant_user_id), 256), NULL)
    ) STORED,

  PRIMARY KEY (id),
  UNIQUE KEY uq_links_ulid (ulid),
  UNIQUE KEY uq_links_one_active (active_pair_hash),
  KEY idx_links_agency_status (agency_id, status),
  KEY idx_links_applicant (applicant_user_id, status),
  KEY idx_links_expiry (status, expires_at),
  CONSTRAINT fk_links_agency FOREIGN KEY (agency_id)
    REFERENCES agencies (id) ON DELETE CASCADE,
  CONSTRAINT fk_links_applicant FOREIGN KEY (applicant_user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_links_inviter FOREIGN KEY (invited_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_links_revoker FOREIGN KEY (revoked_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_links_status CHECK (status IN ('pending','active','revoked','expired')),
  CONSTRAINT chk_links_consent CHECK (
    status <> 'active' OR (consented_at IS NOT NULL AND consent_method IS NOT NULL)
  ),
  CONSTRAINT chk_links_revoked CHECK (
    status <> 'revoked' OR (revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL)
  )
) ENGINE=InnoDB;
```

`chk_links_consent` is the schema-level expression of PRD FR-AG-02: a link cannot be active without a recorded consent timestamp and method. Consent is not an application-layer nicety here; an active link without it cannot exist.

### 4.10 Notifications

```sql
CREATE TABLE notifications (
  id              CHAR(36) NOT NULL,          -- Laravel's UUID convention
  type            VARCHAR(191) NOT NULL,
  notifiable_type VARCHAR(191) NOT NULL,
  notifiable_id   BIGINT UNSIGNED NOT NULL,
  data            JSON NOT NULL,
  application_id  BIGINT UNSIGNED NULL,
  read_at         TIMESTAMP NULL,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_notifications_notifiable (notifiable_type, notifiable_id, read_at),
  KEY idx_notifications_app (application_id),
  CONSTRAINT fk_notifications_app FOREIGN KEY (application_id)
    REFERENCES visa_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notification_templates (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_key   VARCHAR(64) NOT NULL,
  locale      VARCHAR(10) NOT NULL DEFAULT 'en',
  channel     VARCHAR(16) NOT NULL DEFAULT 'mail',
  subject     VARCHAR(191) NULL,
  body        TEXT NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_templates_event (event_key, locale, channel),
  CONSTRAINT fk_notif_templates_user FOREIGN KEY (updated_by_user_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_notif_channel CHECK (channel IN ('mail','database'))
) ENGINE=InnoDB;
```

`application_id` is denormalised onto `notifications` purely so the notification centre can filter by application without decoding the JSON payload — a query that runs on every load of App Flow APP-23.

### 4.11 Reporting read models

```sql
CREATE TABLE daily_application_metrics (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date         DATE NOT NULL,
  country_id          BIGINT UNSIGNED NOT NULL,
  visa_type_id        BIGINT UNSIGNED NOT NULL,
  submitted_count     INT UNSIGNED NOT NULL DEFAULT 0,
  approved_count      INT UNSIGNED NOT NULL DEFAULT 0,
  rejected_count      INT UNSIGNED NOT NULL DEFAULT 0,
  withdrawn_count     INT UNSIGNED NOT NULL DEFAULT 0,
  pending_count       INT UNSIGNED NOT NULL DEFAULT 0,
  avg_processing_hours DECIMAL(10,2) NULL,
  sla_breach_count    INT UNSIGNED NOT NULL DEFAULT 0,
  computed_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dam (metric_date, country_id, visa_type_id),
  KEY idx_dam_date (metric_date),
  CONSTRAINT fk_dam_country FOREIGN KEY (country_id)
    REFERENCES countries (id) ON DELETE CASCADE,
  CONSTRAINT fk_dam_visa_type FOREIGN KEY (visa_type_id)
    REFERENCES visa_types (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE daily_payment_metrics (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date             DATE NOT NULL,
  provider                VARCHAR(32) NOT NULL,
  currency                CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  gross_amount_minor      BIGINT NOT NULL DEFAULT 0,
  successful_count        INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count            INT UNSIGNED NOT NULL DEFAULT 0,
  refund_amount_minor     BIGINT NOT NULL DEFAULT 0,
  reconciliation_variance_minor BIGINT NOT NULL DEFAULT 0,
  computed_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dpm (metric_date, provider, currency),
  KEY idx_dpm_date (metric_date)
) ENGINE=InnoDB;

CREATE TABLE officer_performance_metrics (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date          DATE NOT NULL,
  officer_user_id      BIGINT UNSIGNED NOT NULL,
  assigned_count       INT UNSIGNED NOT NULL DEFAULT 0,
  completed_count      INT UNSIGNED NOT NULL DEFAULT 0,
  avg_turnaround_hours DECIMAL(10,2) NULL,
  rework_count         INT UNSIGNED NOT NULL DEFAULT 0,
  sla_breach_count     INT UNSIGNED NOT NULL DEFAULT 0,
  computed_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_opm (metric_date, officer_user_id),
  CONSTRAINT fk_opm_officer FOREIGN KEY (officer_user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE document_rejection_metrics (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date         DATE NOT NULL,
  document_type_id    BIGINT UNSIGNED NOT NULL,
  rejection_reason_id BIGINT UNSIGNED NULL,
  rejection_count     INT UNSIGNED NOT NULL DEFAULT 0,
  resubmission_count  INT UNSIGNED NOT NULL DEFAULT 0,
  computed_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_drm (metric_date, document_type_id, rejection_reason_id),
  CONSTRAINT fk_drm_doc_type FOREIGN KEY (document_type_id)
    REFERENCES document_types (id) ON DELETE CASCADE,
  CONSTRAINT fk_drm_reason FOREIGN KEY (rejection_reason_id)
    REFERENCES rejection_reasons (id) ON DELETE SET NULL
) ENGINE=InnoDB;
```

Each has a natural unique key so the nightly jobs are idempotent via `upsert` — re-running any date produces identical rows rather than duplicates (Implementation Prompts M9.1).

### 4.12 Compliance

```sql
CREATE TABLE audit_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id  BIGINT UNSIGNED NULL,        -- NULL = system
  actor_type     VARCHAR(16) NOT NULL DEFAULT 'system',
  on_behalf_of_user_id BIGINT UNSIGNED NULL,  -- agent acting for applicant
  action         VARCHAR(100) NOT NULL,
  auditable_type VARCHAR(191) NULL,
  auditable_id   BIGINT UNSIGNED NULL,
  application_id BIGINT UNSIGNED NULL,
  ip_address     VARBINARY(16) NULL,
  user_agent     VARCHAR(500) NULL,
  old_values     JSON NULL,
  new_values     JSON NULL,
  metadata       JSON NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_auditable (auditable_type, auditable_id, created_at),
  KEY idx_audit_actor (actor_user_id, created_at),
  KEY idx_audit_action (action, created_at),
  KEY idx_audit_app (application_id, created_at),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_behalf FOREIGN KEY (on_behalf_of_user_id)
    REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

`ON DELETE RESTRICT` on `actor_user_id` means **a user who has ever acted cannot be hard-deleted**. This is deliberate and is why `users` carries `deleted_at` — departing staff are soft-deleted, preserving the attribution of every decision they made. `on_behalf_of_user_id` satisfies PRD FR-AG-05: agent actions name the individual agent *and* the applicant they acted for.

```sql
CREATE TABLE export_logs (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ulid                CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  export_type         VARCHAR(64) NOT NULL,
  filters_json        JSON NULL,
  ip_address          VARBINARY(16) NULL,
  row_count           INT UNSIGNED NULL,
  file_path           VARCHAR(500) NULL,
  status              VARCHAR(16) NOT NULL DEFAULT 'queued',
  expires_at          TIMESTAMP NULL,
  downloaded_at       TIMESTAMP NULL,
  download_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at          TIMESTAMP NULL,
  updated_at          TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exports_ulid (ulid),
  KEY idx_exports_user (requested_by_user_id, created_at),
  KEY idx_exports_expiry (status, expires_at),
  CONSTRAINT fk_exports_user FOREIGN KEY (requested_by_user_id)
    REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_exports_status CHECK (status IN
    ('queued','processing','ready','expired','failed'))
) ENGINE=InnoDB;

CREATE TABLE tracking_lookup_attempts (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tracking_number VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
  tracking_hash   CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ip_address      VARBINARY(16) NOT NULL,
  successful      TINYINT(1) NOT NULL DEFAULT 0,
  stage           VARCHAR(16) NOT NULL,     -- lookup | otp
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tracking_ip (ip_address, created_at),
  KEY idx_tracking_hash (tracking_hash, created_at),
  CONSTRAINT chk_tracking_stage CHECK (stage IN ('lookup','otp'))
) ENGINE=InnoDB;
```

Two independent indexes because PRD FR-PT-02 requires throttling **by IP and by tracking number independently**. `tracking_hash` lets an unknown tracking number be rate-limited without storing the guessed value — the enumeration attempt is counted without recording the attacker's probe strings.

### 4.13 Laravel infrastructure

Standard, unmodified: `migrations`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `personal_access_tokens`. Queue and cache use Redis in production; the tables exist for local development and failed-job inspection.

---

## 5. Relationship and Delete-Behaviour Matrix

| Child | Parent | On delete | Why |
|---|---|---|---|
| `applicant_profiles` | `users` | CASCADE | Profile has no meaning without the account |
| `visa_applications` | `users` | RESTRICT | An application is a record of state action |
| `visa_applications` | `visa_types`, `form_templates`, `countries` | RESTRICT | Reference data behind a live record is never removed |
| `application_answers` | `visa_applications` | CASCADE | Answers are part of the application |
| `application_snapshots` | `visa_applications` | RESTRICT | **Blocks hard-deletion of any submitted application** |
| `application_status_histories` | `visa_applications` | RESTRICT | Append-only evidence |
| `application_documents` | `visa_applications` | CASCADE | |
| `document_versions` | `application_documents` | RESTRICT | Version history survives |
| `payments` | `visa_applications` | RESTRICT | Financial records outlive applications |
| `invoices`, `refunds` | `payments` | RESTRICT | Statutory financial retention |
| `payment_items` | `payments` | CASCADE | Lines belong to their payment |
| `appointments` | `visa_applications`, `appointment_slots` | RESTRICT | Attendance is evidence |
| `agent_applicant_links` | `agencies`, `users` | CASCADE | Consent record follows its parties |
| `audit_logs` | `users` | RESTRICT | **Actors are never hard-deleted** |

**The pattern:** `CASCADE` where the child is a *component* of the parent; `RESTRICT` where the child is *evidence*. Evidence wins.

---

## 6. Generated Columns

MySQL 8 has no partial indexes. Six generated columns emulate them.

| Table | Column | Emulates |
|---|---|---|
| `form_templates` | `active_visa_type_id` | One active template per visa type |
| `agent_applicant_links` | `active_pair_hash` | One active link per (agency, applicant) |
| `visa_applications` | `active_tracking` *(below)* | Tracking uniqueness excluding soft-deleted |
| `application_documents` | — | Natural unique key suffices |
| `payments` | — | `(provider, provider_payment_id)` suffices |
| `refunds` | — | Constraint-based |

```sql
-- Soft-deleted applications must not block reuse of a tracking number
-- in the (rare) case of an administrative re-issue.
ALTER TABLE visa_applications
  ADD COLUMN active_tracking VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin
    GENERATED ALWAYS AS (IF(deleted_at IS NULL, tracking_number, NULL)) STORED,
  ADD UNIQUE KEY uq_apps_active_tracking (active_tracking);
```

---

## 7. CHECK Constraint Catalogue

24 constraints, defined inline above. Grouped by what they protect:

| Category | Constraints |
|---|---|
| **Enumerated values** | `chk_users_status`, `chk_users_type`, `chk_apps_status`, `chk_apps_public_status`, `chk_docs_status`, `chk_versions_scan`, `chk_payments_status`, `chk_refunds_status`, `chk_appointments_status`, `chk_agencies_status`, `chk_links_status`, `chk_slots_type`, `chk_exports_status` |
| **Money integrity** | `chk_fees_nonneg`, `chk_payments_amount`, `chk_payments_refund_bound`, `chk_payment_item_amount`, `chk_invoices_total`, `chk_refunds_amount`, `chk_apps_fee_pair` |
| **Separation of duties** | `chk_refunds_four_eyes`, `chk_apps_four_eyes` |
| **Completeness pairs** | `chk_apps_decision_complete`, `chk_docs_review`, `chk_links_consent`, `chk_agencies_vetted`, `chk_apps_agent_pair` |
| **Capacity and ranges** | `chk_slots_booked`, `chk_slots_window`, `chk_fees_window`, `chk_profiles_passport_dates` |
| **Mandatory reasons** | `chk_status_hist_reason`, `chk_docs_rejection` |

> MySQL enforces `CHECK` from **8.0.16**. On 8.0.15 or earlier they parse and are silently ignored — a dangerous failure mode. Add a migration guard: `SELECT VERSION()` and abort if below 8.0.16.

---

## 8. Triggers

19 triggers. All follow one pattern: `SIGNAL SQLSTATE '45000'` with an explanatory message.

### 8.1 Append-only enforcement (10 triggers)

Five tables are append-only under PRD BR-02. Each gets a `BEFORE UPDATE` and a `BEFORE DELETE` trigger.

```sql
DELIMITER $$

-- audit_logs ────────────────────────────────────────────────
CREATE TRIGGER trg_audit_logs_no_update BEFORE UPDATE ON audit_logs
FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'audit_logs is append-only: rows cannot be updated';
END$$

CREATE TRIGGER trg_audit_logs_no_delete BEFORE DELETE ON audit_logs
FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'audit_logs is append-only: rows cannot be deleted';
END$$

-- application_status_histories ──────────────────────────────
CREATE TRIGGER trg_status_hist_no_update BEFORE UPDATE ON application_status_histories
FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'application_status_histories is append-only';
END$$

CREATE TRIGGER trg_status_hist_no_delete BEFORE DELETE ON application_status_histories
FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'application_status_histories is append-only';
END$$

-- application_snapshots ─────────────────────────────────────
CREATE TRIGGER trg_snapshots_no_update BEFORE UPDATE ON application_snapshots
FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'application_snapshots are immutable (PRD BR-01)';
END$$

CREATE TRIGGER trg_snapshots_no_delete BEFORE DELETE ON application_snapshots
FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'application_snapshots are immutable (PRD BR-01)';
END$$

DELIMITER ;
```

The same pair applies to `payment_webhook_events` (update permitted **only** to set `processed_at`, `processing_error`, `attempt_count`, and `payment_id` — see §8.2) and `document_versions`.

### 8.2 Selective immutability (5 triggers)

Some tables permit narrow updates. These triggers allow exactly those columns.

```sql
DELIMITER $$

-- Webhook events: only processing metadata may change.
CREATE TRIGGER trg_webhook_selective_update BEFORE UPDATE ON payment_webhook_events
FOR EACH ROW BEGIN
  IF NEW.provider <> OLD.provider
     OR NEW.provider_event_id <> OLD.provider_event_id
     OR NEW.event_type <> OLD.event_type
     OR NOT (NEW.payload_json <=> OLD.payload_json)
     OR NEW.received_at <> OLD.received_at THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Webhook event payload and identity are immutable';
  END IF;
END$$

-- Document versions: only scan and image-check outcomes may change.
CREATE TRIGGER trg_versions_selective_update BEFORE UPDATE ON document_versions
FOR EACH ROW BEGIN
  IF NEW.sha256_checksum <> OLD.sha256_checksum
     OR NEW.storage_path <> OLD.storage_path
     OR NEW.file_size_bytes <> OLD.file_size_bytes
     OR NEW.version_number <> OLD.version_number
     OR NEW.application_document_id <> OLD.application_document_id THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Document version identity and content are immutable';
  END IF;
END$$

-- Published form templates cannot be edited (PRD FR-CF-04).
CREATE TRIGGER trg_templates_published_immutable BEFORE UPDATE ON form_templates
FOR EACH ROW BEGIN
  IF OLD.published_at IS NOT NULL
     AND (NOT (NEW.schema_json <=> OLD.schema_json)
          OR NEW.version <> OLD.version
          OR NEW.visa_type_id <> OLD.visa_type_id) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Published form templates are immutable: create a new version';
  END IF;
END$$

-- Core application identity is fixed once submitted (PRD BR-14, BR-15).
CREATE TRIGGER trg_apps_identity_immutable BEFORE UPDATE ON visa_applications
FOR EACH ROW BEGIN
  IF NEW.tracking_number <> OLD.tracking_number THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'tracking_number is immutable';
  END IF;
  IF OLD.submitted_at IS NOT NULL THEN
    IF NEW.form_template_id <> OLD.form_template_id THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'form_template_id is fixed at draft creation (PRD BR-14)';
    END IF;
    IF NEW.fee_total_minor <> OLD.fee_total_minor
       OR NEW.fee_currency <> OLD.fee_currency THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Fee is frozen at submission (PRD BR-15)';
    END IF;
    IF NEW.submitted_at <> OLD.submitted_at THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'submitted_at is immutable';
    END IF;
  END IF;
END$$

-- Decisions are final (PRD FR-OR-09).
CREATE TRIGGER trg_apps_decision_final BEFORE UPDATE ON visa_applications
FOR EACH ROW BEGIN
  IF OLD.decided_at IS NOT NULL
     AND (NEW.decision <> OLD.decision
          OR NEW.decided_by_user_id <> OLD.decided_by_user_id
          OR NEW.decided_at <> OLD.decided_at) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'A recorded decision cannot be altered: reverse it with a new transition';
  END IF;
END$$

DELIMITER ;
```

### 8.3 Derived-state maintenance (4 triggers)

```sql
DELIMITER $$

-- Keep the payment's refunded total consistent with completed refunds.
CREATE TRIGGER trg_refund_completed_rollup AFTER UPDATE ON refunds
FOR EACH ROW BEGIN
  IF NEW.status = 'completed' AND OLD.status <> 'completed' THEN
    UPDATE payments
      SET refunded_amount_minor = refunded_amount_minor + NEW.amount_minor,
          status = IF(refunded_amount_minor + NEW.amount_minor >= amount_minor,
                      'refunded', 'partially_refunded')
      WHERE id = NEW.payment_id;
  END IF;
END$$

-- An application's public_status must always agree with its internal status.
CREATE TRIGGER trg_apps_public_status_sync BEFORE UPDATE ON visa_applications
FOR EACH ROW BEGIN
  IF NEW.status <> OLD.status THEN
    SET NEW.public_status = CASE NEW.status
      WHEN 'draft'                 THEN 'draft'
      WHEN 'submitted'             THEN 'submitted'
      WHEN 'payment_pending'       THEN 'submitted'
      WHEN 'paid'                  THEN 'in_review'
      WHEN 'under_review'          THEN 'in_review'
      WHEN 'document_verification' THEN 'in_review'
      WHEN 'info_requested'        THEN 'action_required'
      WHEN 'resubmitted'           THEN 'in_review'
      WHEN 'interview_scheduled'   THEN 'appointment_scheduled'
      WHEN 'decision_pending'      THEN 'in_review'
      WHEN 'approved'              THEN 'decision_made'
      WHEN 'rejected'              THEN 'decision_made'
      WHEN 'withdrawn'             THEN 'withdrawn'
      WHEN 'closed'                THEN 'closed'
    END;
    SET NEW.lock_version = OLD.lock_version + 1;
  END IF;
END$$

CREATE TRIGGER trg_apps_public_status_insert BEFORE INSERT ON visa_applications
FOR EACH ROW BEGIN
  IF NEW.public_status IS NULL OR NEW.public_status = '' THEN
    SET NEW.public_status = 'draft';
  END IF;
END$$

-- Blocking a slot must not orphan existing bookings.
CREATE TRIGGER trg_slots_block_guard BEFORE UPDATE ON appointment_slots
FOR EACH ROW BEGIN
  IF NEW.is_blocked = 1 AND OLD.is_blocked = 0 AND OLD.booked_count > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cannot block a slot with existing bookings: cancel or move them first';
  END IF;
  IF NEW.capacity < OLD.booked_count THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Capacity cannot be reduced below existing bookings';
  END IF;
END$$

DELIMITER ;
```

`trg_apps_public_status_sync` is the one derived-state trigger I would defend hardest. The public-status mapping is a **presentation invariant with a compliance edge** — an applicant seeing a stale or wrong public status is a service failure, and PRD FR-AP-07 requires the mapping. Placing it in the database means no code path — controller, console command, data fix, or future integration — can produce a row where the two disagree.

### 8.4 What deliberately stays out of triggers

| Not a trigger | Why |
|---|---|
| Status transition validation | The state machine has 40+ rules and must return actionable errors naming the unmet condition. A `SIGNAL` message cannot link to a screen. |
| `booked_count` increment | The application increments it under an explicit row lock inside the booking transaction; a trigger would hide the concurrency model that §M6.2 tests directly. |
| Audit-log writing | Audit entries need request context (IP, user agent, on-behalf-of) that the database does not have. |
| Fee resolution | Ambiguity detection must raise a *configuration* error to an administrator, not a SQL error to an applicant. |
| SLA computation | Business-hours arithmetic across a holiday calendar; belongs in a job. |

---

## 9. Stored Functions

Three functions, each justified by being needed *inside SQL* — for reporting, BI tools, and ad-hoc reconciliation queries where application code is not in the loop.

```sql
DELIMITER $$

-- Render minor units as a decimal for reporting and BI.
-- The application NEVER uses this for arithmetic — bcmath does that.
CREATE FUNCTION fn_minor_to_decimal(p_amount BIGINT, p_currency CHAR(3))
RETURNS DECIMAL(20,4)
DETERMINISTIC READS SQL DATA
BEGIN
  DECLARE v_exp TINYINT UNSIGNED DEFAULT 2;
  SELECT minor_unit_exponent INTO v_exp FROM currencies WHERE code = p_currency;
  RETURN p_amount / POW(10, IFNULL(v_exp, 2));
END$$

-- Is this date a working day for SLA purposes?
CREATE FUNCTION fn_is_business_day(p_date DATE, p_location_id BIGINT UNSIGNED)
RETURNS TINYINT(1)
DETERMINISTIC READS SQL DATA
BEGIN
  IF DAYOFWEEK(p_date) IN (1, 7) THEN RETURN 0; END IF;
  IF EXISTS (
    SELECT 1 FROM holidays
    WHERE holiday_date = p_date
      AND (location_id IS NULL OR location_id = p_location_id)
  ) THEN RETURN 0; END IF;
  RETURN 1;
END$$

-- Business days between two dates, for reporting queries.
CREATE FUNCTION fn_business_days_between(p_start DATE, p_end DATE, p_location_id BIGINT UNSIGNED)
RETURNS INT
DETERMINISTIC READS SQL DATA
BEGIN
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_cur DATE DEFAULT p_start;
  WHILE v_cur < p_end DO
    IF fn_is_business_day(v_cur, p_location_id) = 1 THEN
      SET v_count = v_count + 1;
    END IF;
    SET v_cur = DATE_ADD(v_cur, INTERVAL 1 DAY);
  END WHILE;
  RETURN v_count;
END$$

DELIMITER ;
```

**No MySQL `EVENT` scheduler.** All scheduling is Laravel's, running on the worker host under a distributed lock (Tech Stack §5.5). Two schedulers would be two sources of truth.

---

## 10. Index Strategy — Query to Index Mapping

Every index earns its place against a named screen.

| Screen | Query | Index |
|---|---|---|
| APP-02 Applications list | `WHERE user_id = ? AND status IN (...)` | `idx_apps_user_status` |
| APP-05 Hub | `WHERE tracking_number = ?` | `uq_apps_tracking` |
| APP-05 Sections | `WHERE application_id = ? ORDER BY section_key` | `idx_answers_section` |
| APP-07 Documents | `WHERE application_id = ?` | `uq_docs_app_type` |
| APP-13 Payment poll | `WHERE application_id = ? AND status = ?` | `idx_payments_app` |
| APP-17 Slot availability | `WHERE location_id = ? AND appointment_type = ? AND starts_at BETWEEN ? AND ? AND is_blocked = 0` | `idx_slots_availability` |
| APP-23 Notifications | `WHERE notifiable_id = ? AND read_at IS NULL` | `idx_notifications_notifiable` |
| PUB-14 Tracking | `WHERE tracking_number = ?` | `uq_apps_tracking` |
| PUB-14 Throttle | `WHERE ip_address = ? AND created_at > ?` | `idx_tracking_ip` |
| OFF-03 Queue | `WHERE assigned_to_user_id = ? AND status IN (...) ORDER BY sla_due_at` | `idx_apps_assigned_status` + `idx_apps_sla` |
| OFF-03 At-risk view | `WHERE sla_state IN ('at_risk','breached') ORDER BY sla_due_at` | `idx_apps_sla` |
| OFF-04 All apps | `WHERE status = ? ORDER BY submitted_at` | `idx_apps_status_submitted` |
| OFF-13 Roster | `WHERE slot_id IN (...) AND status = ?` | `idx_appointments_roster` |
| AGT-09 Agent apps | `WHERE agency_id = ? AND status IN (...)` | `idx_apps_agency` |
| AGT guard (every request) | `WHERE agency_id = ? AND applicant_user_id = ? AND status = 'active'` | `idx_links_agency_status` |
| ADM-15 Reconciliation | `WHERE status = ? AND paid_at BETWEEN ? AND ? AND currency = ?` | `idx_payments_recon` |
| ADM-16 Refunds | `WHERE status = 'requested' ORDER BY requested_at` | `idx_refunds_pending` |
| ADM-19 Audit search | `WHERE actor_user_id = ? AND created_at BETWEEN ? AND ?` | `idx_audit_actor` |
| Webhook idempotency | `WHERE provider = ? AND provider_event_id = ?` | `uq_webhook_event` |
| Scan worker | `WHERE virus_scan_status = 'pending' ORDER BY created_at` | `idx_versions_scan` |
| Fee resolution | `WHERE visa_type_id = ? AND (nationality_country_id = ? OR IS NULL) AND is_active = 1 AND valid_from <= ?` | `idx_fees_resolution` |
| Nightly metrics | `WHERE submitted_at BETWEEN ? AND ?` | `idx_apps_reporting` |
| Duplicate passport check | `WHERE passport_number_hash = ?` | `idx_profiles_passport_hash` |

**Index budget on `visa_applications`: 7 secondary indexes plus 2 unique.** At `BIGINT` PKs this costs ~72 bytes of row-pointer overhead per row across all indexes; at `CHAR(26)` it would have been ~234. That difference is the whole argument for D-2.

---

## 11. Authentication Flows

### 11.1 Guards

```php
// config/auth.php
'guards' => [
    'web'     => ['driver' => 'session', 'provider' => 'applicants'],
    'agent'   => ['driver' => 'session', 'provider' => 'agents'],
    'staff'   => ['driver' => 'session', 'provider' => 'staff'],
    'sanctum' => ['driver' => 'sanctum', 'provider' => 'applicants'],  // Phase 2
],
```

Three session guards on three hosts with three cookie names. `user_type` scopes each provider, so an applicant session can never resolve a staff record even if a cookie is replayed across hosts.

### 11.2 Registration and verification

```
POST /register
  ├─ validate; check users.email uniqueness
  ├─ INSERT users (status='pending', user_type='applicant')
  ├─ INSERT audit_logs (action='user.registered')
  ├─ send signed verification link (60 min TTL)
  └─ RESPONSE IS IDENTICAL whether or not the email already exists
       └─ if it existed: email the existing address instead
          (PRD PUB-05 — the screen must not confirm account existence)

GET /email/verify/{id}/{hash}
  ├─ verify signature
  ├─ UPDATE users SET email_verified_at, status='active'
  ├─ assign role 'applicant'
  └─ INSERT audit_logs (action='user.email_verified')
```

### 11.3 Login

```
POST /login
  ├─ rate limit: 5 per email per 15 min · 20 per IP per 15 min
  ├─ INSERT login_attempts (successful=0) BEFORE evaluating
  │     └─ recorded even for unknown emails (user_id NULL)
  ├─ constant-time credential check
  ├─ status='suspended'      → SYS-10, failure_reason='suspended'
  ├─ email_verified_at NULL  → PUB-06, failure_reason='unverified'
  ├─ session regenerate
  ├─ user_type='staff' or MFA enrolled → MFA challenge
  └─ UPDATE login_attempts SET successful=1
     UPDATE users SET last_login_at, last_login_ip
     INSERT audit_logs (action='auth.login')
```

**One generic message for every credential failure**, with constant-time comparison so response timing does not distinguish an unknown email from a wrong password.

### 11.4 MFA — mandatory for all staff

```
Enrolment:  generate TOTP secret → encrypted into user_mfa_methods
            → verify one code → confirmed_at set
            → 10 recovery codes generated, bcrypt-hashed into mfa_recovery_codes
            → UPDATE users SET mfa_enabled_at

Challenge:  6-digit TOTP, ±1 window drift
            → or one recovery code (marked used_at, single-use)
            → 5 failures → lock + email the account owner
            → UPDATE user_mfa_methods SET last_used_at

Middleware: EnsureMfaEnrolled — any staff user without mfa_enabled_at
            can reach ONLY the enrolment routes, in every non-local environment
```

### 11.5 Public tracking — two-stage

```
Stage 1  POST /track
  ├─ INSERT tracking_lookup_attempts (stage='lookup', tracking_hash=SHA2(input))
  ├─ throttle by ip_address AND by tracking_hash, independently
  ├─ look up the application
  └─ ALWAYS respond identically, with a fixed minimum response time,
     whether or not the number exists     ← prevents enumeration
     └─ if it exists: email a 6-digit OTP (10 min TTL, Redis)

Stage 2  POST /track/verify
  ├─ INSERT tracking_lookup_attempts (stage='otp')
  ├─ throttle: 5 attempts per tracking_hash
  └─ on success: return public_status, next action, last-updated date ONLY
     (no name, no documents, no officer, no internal status)
```

The fixed minimum response time is essential and easy to omit. Without it, a lookup that skips the email send returns measurably faster, and timing alone reveals which tracking numbers are real.

### 11.6 Agent linkage — verified per request

```php
// Middleware EnsureActiveLinkage — NEVER cached in the session (PRD FR-AG-04)
$link = AgentApplicantLink::where('agency_id', $agencyId)
    ->where('applicant_user_id', $application->user_id)
    ->where('status', 'active')
    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
    ->first();

abort_if(! $link, 404);   // 404, not 403 — existence must not be confirmed
```

Served by `idx_links_agency_status`. Revocation therefore takes effect on the agent's very next request, with no cache window — the property that makes PRD FR-AG-04 a security control rather than a UI nicety.

---

## 12. Authorisation Model

### 12.1 Roles

`applicant` · `agent` · `case_officer` · `senior_officer` · `document_verifier` · `finance_officer` · `support_staff` · `admin` · `super_admin`

### 12.2 Permission naming

`<resource>.<action>[.<scope>]` — for example `application.view.assigned`, `application.approve`, `refund.approve`, `audit.view.all`.

### 12.3 Policy map

| Model | Policy | Scoping rule |
|---|---|---|
| `VisaApplication` | `VisaApplicationPolicy` | Owner · active linkage · assigned officer · senior/admin all |
| `ApplicantProfile` | `ApplicantProfilePolicy` | Owner · linkage scope · staff read with field redaction |
| `ApplicationDocument` | `ApplicationDocumentPolicy` | Inherits the application; **preview blocked unless scan clean** |
| `DocumentVersion` | `DocumentVersionPolicy` | As above; audit written before the signed URL is issued |
| `Payment` | `PaymentPolicy` | Owner · finance read · officer read-only · admin |
| `Refund` | `RefundPolicy` | Finance initiate · admin approve · **approver ≠ requester** |
| `Appointment` | `AppointmentPolicy` | Owner · linkage · officer schedule |
| `AgentApplicantLink` | `LinkPolicy` | Applicant revoke · agency view own |
| `Agency` | `AgencyPolicy` | Own members · admin vet |
| `AuditLog` | `AuditLogPolicy` | Super admin all · scoped views below |
| `FormTemplate`, `VisaType`, `VisaFee`, `ServiceLocation` | Config policies | Admin write · all read |

### 12.4 Separation of duties — enforced in three layers

| Rule | Database | Application | Test |
|---|---|---|---|
| Refund approver ≠ requester | `chk_refunds_four_eyes` | `ApproveRefund` guard | M4.5 |
| Countersigner ≠ decider | `chk_apps_four_eyes` | `ApprovalGuard` | M5.4 |
| Finance cannot alter application status | — | `VisaApplicationPolicy` | M4.5 |
| Officer cannot decide unassigned cases | — | `ApprovalGuard` | M5.4 |
| Agent cannot withdraw | — | No route exists | M7.3 |

Two of these are in the schema because they are *invariants of the institution*, not of the code. An emergency console command written at 2am to fix a stuck refund must not be able to violate them.

---

## 13. Encryption and PII

### 13.1 Encrypted columns

| Table | Column | Method | Searchable via |
|---|---|---|---|
| `applicant_profiles` | `passport_number_encrypted` | Laravel `encrypted` cast (AES-256-CBC, `APP_KEY`) | `passport_number_hash` |
| `application_answers` | `value_json` when `is_encrypted = 1` | Laravel `encrypted` cast | Not searchable, by design |
| `user_mfa_methods` | `secret_encrypted` | Laravel `encrypted` cast | Not searchable |

Application-layer encryption is chosen over MySQL's `AES_ENCRYPT` for one decisive reason: the key never enters the database process, so a database compromise, a leaked backup, or a replica snapshot yields ciphertext only. MySQL-side encryption would place the key in query text and the general log.

### 13.2 The blind index, and its limit

```php
hash_hmac('sha256', $this->normalisePassport($number), config('app.blind_index_key'))
```

Normalisation is uppercase, alphanumeric only. The pepper lives in the secret store, never in the database.

**Honest limitation:** a blind index leaks equality. An attacker with database access can tell that two profiles share a passport number, even without learning it. That is an acceptable trade for duplicate detection, but it should be a conscious acceptance rather than a discovery — and it is why the pepper's separation from the database matters so much.

### 13.3 Never stored

Card numbers · CVV · any payment credential · biometric data of any kind · plaintext passwords · plaintext MFA secrets or recovery codes · passport numbers in `audit_logs`, `notifications`, or any log.

---

## 14. Operational Notes

### 14.1 Transaction isolation

```sql
SET GLOBAL transaction_isolation = 'READ-COMMITTED';
```

MySQL defaults to `REPEATABLE-READ`, which takes gap locks on range scans. The appointment-booking path range-scans `appointment_slots` by `(location_id, appointment_type, starts_at)` and then locks a row — under `REPEATABLE-READ` that produces gap-lock contention between applicants booking *different* slots at the same location. `READ-COMMITTED` removes the gap locks; the explicit `SELECT ... FOR UPDATE` still provides the correctness the booking model needs.

### 14.2 Booking transaction

```sql
START TRANSACTION;
  SELECT id, capacity, booked_count
    FROM appointment_slots
   WHERE id = ? AND is_blocked = 0
     FOR UPDATE;                                  -- row lock

  -- application re-checks booked_count < capacity here

  INSERT INTO appointments (...) VALUES (...);
  UPDATE appointment_slots
     SET booked_count = booked_count + 1
   WHERE id = ?;                                  -- chk_slots_booked is the backstop
COMMIT;
```

### 14.3 JSON path indexing

Where a JSON path is filtered often, add a stored generated column and index that. MySQL cannot index inside `JSON` directly.

```sql
ALTER TABLE visa_type_document_requirements
  ADD COLUMN condition_type VARCHAR(32)
    GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(condition_rules, '$.type'))) STORED,
  ADD KEY idx_vtdr_condition_type (condition_type);
```

### 14.4 Retention and deletion

| Data | Retention | Mechanism |
|---|---|---|
| Applications and snapshots | Statutory *(PRD OD-3)* | Soft delete; `RESTRICT` blocks hard deletion |
| Documents | Statutory, then secure object deletion | Job; deletion itself audited |
| Audit logs | ≥ the records they describe | Append-only; partition by year when volume requires |
| Payments and invoices | Statutory financial | `RESTRICT` throughout |
| Unsubmitted drafts | 12 months (proposed) | Job; applicant notified first |
| Notifications | 24 months | Job |
| Export files | 7 days | `expires_at` + cleanup job |
| `login_attempts` | 12 months | Job |
| `tracking_lookup_attempts` | 90 days | Job |

**Right-to-erasure interacts with statutory retention.** The design: erasure nulls contact details and non-statutory profile fields on `users` and `applicant_profiles` while preserving `visa_applications`, snapshots, ledger, and audit rows. Legal must confirm the boundary — this is PRD OD-3 and remains open.

### 14.5 Migration order

```
01 currencies, countries
02 users → applicant_profiles
03 permissions, roles, pivots (Spatie)
04 user_mfa_methods, mfa_recovery_codes, login_attempts, sessions, password_resets
05 visa_types, visa_fees, document_types, rejection_reasons
06 service_locations, holidays, visa_type_document_requirements
07 form_templates
08 agencies, agency_users            ← before visa_applications (FK agency_id)
09 visa_applications                 ← hub
10 application_answers, application_snapshots, application_status_histories
11 application_documents → document_versions → ALTER for current_version_id
12 payments, payment_items, payment_webhook_events, invoices, refunds
13 appointment_slots, appointments
14 agent_applicant_links
15 review_notes, information_requests, information_request_items
16 notifications, notification_templates
17 metrics tables ×4
18 audit_logs, export_logs, tracking_lookup_attempts
19 generated columns + their unique indexes
20 CHECK constraints (guard: abort below MySQL 8.0.16)
21 triggers ×19
22 stored functions ×3
```

Step 11 needs the two-phase `ALTER` because `application_documents.current_version_id` and `document_versions.application_document_id` reference each other.

---

## 15. Open Items

| ID | Item | Blocks |
|---|---|---|
| SCH-1 | PRD OD-3 — statutory retention periods; §14.4 has placeholders | Retention jobs |
| SCH-2 | PRD OD-6 — tax treatment; `tax_minor` exists but the rule does not | M4.2 |
| SCH-3 | PRD OD-7 — refund policy percentages and eligible statuses | M4.5, Content Guidelines §7.5 |
| SCH-4 | App Flow AF-5 — does a reschedule reset the SLA clock? `sla_paused_seconds` exists to support either answer | M5.1, M6.2 |
| SCH-5 | Confirm the mission working calendar; `holidays` needs seeding before SLA is meaningful | M5.1 |
| SCH-6 | Accept the blind-index equality leak (§13.2) explicitly, or drop duplicate detection | M1.1 |
| SCH-7 | Confirm `READ-COMMITTED` with the DBA/managed provider | M0.1 |
| SCH-8 | Amend Implementation Prompts M1.1–M7.1 for the D-2 primary-key reversal | Before M1.1 |
