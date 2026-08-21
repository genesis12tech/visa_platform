<?php

namespace App\Support\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MySQL only enforces CHECK constraints from 8.0.16 (Backend_schema.md §7)
 * — below that they parse and are silently ignored, the worst possible
 * failure mode: a migration "succeeds" while adding no real protection.
 * Every migration that adds a CHECK constraint calls
 * ensureCheckConstraintsSupported() first so an under-versioned MySQL
 * aborts loudly at migration time instead of shipping a silent no-op.
 *
 * MariaDB enforces CHECK from 10.2.1 — this project's verified-production
 * engine (11.8.8) clears that with room to spare — so any MariaDB version
 * string passes unconditionally, mirroring tests/Pest.php's
 * databaseEnforcesCheckConstraints() helper.
 */
trait EnsuresCheckConstraintSupport
{
    protected function ensureCheckConstraintsSupported(): void
    {
        $version = DB::selectOne('SELECT VERSION() as version')->version;

        if (stripos($version, 'mariadb') !== false) {
            return;
        }

        if (version_compare($version, '8.0.16', '<')) {
            throw new RuntimeException(
                'This migration adds CHECK constraints, which MySQL only enforces from 8.0.16 — '.
                "detected {$version}. Below that version CHECK parses and is silently ignored rather ".
                'than rejecting invalid rows (Backend_schema.md §7). Upgrade MySQL before migrating.'
            );
        }
    }
}
