<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a ULID into the model's `ulid` column on creation and routes on it
 * by default. The ULID is never the primary key (Backend_schema.md D-2 — BIGINT
 * auto-increment stays the PK; ULID is the externally-addressable identifier).
 *
 * Models that route on something else instead (e.g. VisaApplication uses its
 * tracking_number) still use this trait to get the `ulid` column populated,
 * and simply override getRouteKeyName().
 */
trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
