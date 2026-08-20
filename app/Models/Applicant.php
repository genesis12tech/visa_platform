<?php

namespace App\Models;

/**
 * Guard-scoped view of `users` for the 'web' guard's provider
 * (Backend_schema.md §11.1). The permanent global scope is the actual
 * security boundary: even a replayed session cookie can never resolve a
 * non-applicant record through this provider, because retrieval itself is
 * scoped, not just the login-time check.
 */
class Applicant extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('user_type', fn ($query) => $query->where('user_type', 'applicant'));
    }
}
