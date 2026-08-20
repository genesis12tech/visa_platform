<?php

namespace App\Models;

/**
 * Guard-scoped view of `users` for the 'agent' guard's provider — see
 * Applicant for the rationale.
 */
class Agent extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('user_type', fn ($query) => $query->where('user_type', 'agent'));
    }
}
