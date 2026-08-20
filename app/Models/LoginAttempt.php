<?php

namespace App\Models;

use App\Casts\IpAddressCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Security log, never addressed publicly — no ulid (Backend_schema.md
 * §4.3, D-2). created_at only: a login attempt is recorded once and never
 * edited. Unknown-email attempts are recorded with a null user_id
 * deliberately (Backend_schema.md §11.3) — this is what makes
 * credential-stuffing detection possible.
 */
class LoginAttempt extends Model
{
    const UPDATED_AT = null;

    protected $attributes = [
        'successful' => false,
        'guard' => 'web',
    ];

    protected $fillable = [
        'email',
        'user_id',
        'successful',
        'failure_reason',
        'ip_address',
        'user_agent',
        'guard',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'ip_address' => IpAddressCast::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
