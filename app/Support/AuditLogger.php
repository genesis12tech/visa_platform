<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes to the append-only audit_logs table (Backend_schema.md §4.12).
 * Deliberately outside app/Services/ — that directory is capped at exactly
 * six business-logic classes (CLAUDE.md Architecture), and this is a
 * cross-cutting logging utility, not a domain service.
 *
 * $options:
 *   actor         User|null   overrides guard resolution (e.g. registration,
 *                              where the acting user isn't yet logged in)
 *   on_behalf_of  User        the applicant an agent acted for (FR-AG-05)
 *   auditable     Model       subject of the action; recorded via
 *                              getMorphClass()/getKey(), never the runtime
 *                              subclass (see User::getMorphClass())
 *   application_id int
 *   old_values    array
 *   new_values    array
 *   metadata      array
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function log(string $action, array $options = []): AuditLog
    {
        $actor = $options['actor'] ?? $this->resolveActor();
        $auditable = $options['auditable'] ?? null;
        $onBehalfOf = $options['on_behalf_of'] ?? null;
        $request = request();

        return AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'actor_type' => $actor !== null ? 'user' : 'system',
            'on_behalf_of_user_id' => $onBehalfOf?->id,
            'action' => $action,
            'auditable_type' => $auditable instanceof Model ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable instanceof Model ? $auditable->getKey() : null,
            'application_id' => $options['application_id'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $options['old_values'] ?? null,
            'new_values' => $options['new_values'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    private function resolveActor(): ?User
    {
        foreach (['web', 'agent', 'staff'] as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }
}
