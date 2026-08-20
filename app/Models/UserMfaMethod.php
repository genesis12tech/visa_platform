<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMfaMethod extends Model
{
    protected $attributes = [
        'type' => 'totp',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'secret_encrypted',
        'confirmed_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'secret_encrypted' => 'encrypted',
            'confirmed_at' => 'datetime',
            'last_used_at' => 'datetime',
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
