<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Backend_schema.md §11.4: 10 recovery codes generated at MFA enrolment,
 * bcrypt-hashed (never stored plaintext), each single-use (used_at set on
 * consumption). created_at only — a row is never edited after creation,
 * only marked used.
 */
class MfaRecoveryCode extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'code_hash',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{0: string, 1: self}
     */
    public static function generateFor(User $user): array
    {
        $plain = Str::random(10);

        $code = self::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($plain),
        ]);

        return [$plain, $code];
    }

    /**
     * @return array<int, array{0: string, 1: self}>
     */
    public static function generateBatchFor(User $user, int $count = 10): array
    {
        return collect(range(1, $count))
            ->map(fn () => self::generateFor($user))
            ->all();
    }
}
