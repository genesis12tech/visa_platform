<?php

namespace App\Models;

use App\Casts\IpAddressCast;
use App\Models\Concerns\HasUlid;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlid, MustVerifyEmail, Notifiable, SoftDeletes;

    /**
     * Roles/permissions are seeded under a single 'web' guard namespace,
     * shared across all three user_types (RolePermissionSeeder) — this is
     * independent of which session guard (web/agent/staff) actually
     * authenticates a user. Without this, Spatie's guard-guessing matches
     * a model class to a config('auth.providers') entry exactly, which
     * fails now that those providers point at the Applicant/Agent/Staff
     * subclasses rather than User itself.
     */
    protected string $guard_name = 'web';

    protected $attributes = [
        'status' => 'pending',
        'user_type' => 'applicant',
        'locale' => 'en',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'status',
        'user_type',
        'locale',
        'suspended_at',
        'suspended_by_user_id',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'last_login_ip' => IpAddressCast::class,
            'mfa_enabled_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_user_id');
    }

    /**
     * `self::class`, not `static::class`, deliberately: Applicant/Agent/Staff
     * are guard-provider views of this same `users` table, not independent
     * entities — every polymorphic relation touching a user (Spatie's
     * model_type, notifications' notifiable_type, anything future) must
     * resolve to the same type string regardless of which subclass happened
     * to load the row, or lookups made through a different subclass later
     * would silently find nothing.
     */
    public function getMorphClass(): string
    {
        return self::class;
    }
}
