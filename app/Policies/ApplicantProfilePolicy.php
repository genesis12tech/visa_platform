<?php

namespace App\Policies;

use App\Models\ApplicantProfile;
use App\Models\User;

/**
 * Backend_schema.md §12.3 scoping rule: "Owner · linkage scope · staff read
 * with field redaction". Agent-linkage scoping is deferred until
 * AgentApplicantLink exists (a later stage); field redaction for staff is a
 * presentation-layer concern, not a boolean policy decision.
 */
class ApplicantProfilePolicy
{
    public function view(User $user, ApplicantProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->can('application.view');
    }

    public function update(User $user, ApplicantProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }

    public function delete(User $user, ApplicantProfile $profile): bool
    {
        return $user->can('user.manage');
    }
}
