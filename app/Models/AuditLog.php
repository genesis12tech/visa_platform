<?php

namespace App\Models;

use App\Casts\IpAddressCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only compliance log (Backend_schema.md §4.12) — rows are only ever
 * inserted, via App\Support\AuditLogger; trg_audit_logs_no_update and
 * trg_audit_logs_no_delete enforce append-only at the database layer as the
 * real backstop, not the application layer.
 */
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $attributes = [
        'actor_type' => 'system',
    ];

    protected $fillable = [
        'actor_user_id',
        'actor_type',
        'on_behalf_of_user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'application_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'ip_address' => IpAddressCast::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_user_id');
    }
}
