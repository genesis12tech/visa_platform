<?php

namespace App\Models\Concerns;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Wires a model's create/update/delete events into audit_logs
 * (Implementation_plan.md S2.8: "wire into ... reference-data changes").
 * Applied to the nine reference-data models so every configuration change
 * — fee amendments especially — leaves a compliance trail from the moment
 * an admin CRUD screen exists to make one, without that screen needing to
 * remember to call AuditLogger itself.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->log(static::auditActionName('created'), [
                'auditable' => $model,
                'new_values' => $model->getAttributes(),
            ]);
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();

            if ($changes === []) {
                return;
            }

            app(AuditLogger::class)->log(static::auditActionName('updated'), [
                'auditable' => $model,
                'old_values' => array_intersect_key($model->getOriginal(), $changes),
                'new_values' => $changes,
            ]);
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->log(static::auditActionName('deleted'), [
                'auditable' => $model,
                'old_values' => $model->getAttributes(),
            ]);
        });
    }

    protected static function auditActionName(string $event): string
    {
        return Str::snake(class_basename(static::class)).'.'.$event;
    }
}
