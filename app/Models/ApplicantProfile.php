<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantProfile extends Model
{
    use HasUlid;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'place_of_birth',
        'sex',
        'nationality_country_id',
        'passport_number_encrypted',
        'passport_issued_at',
        'passport_expires_at',
        'passport_issuing_country_id',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'residence_country_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_number_encrypted' => 'encrypted',
            'passport_issued_at' => 'date',
            'passport_expires_at' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile) {
            if ($profile->isDirty('passport_number_encrypted')) {
                $plain = $profile->passport_number_encrypted;
                $profile->passport_number_hash = $plain === null ? null : self::hashPassportNumber($plain);
            }
        });
    }

    /**
     * Peppered blind index (Backend_schema.md §13.2). Normalises to uppercase
     * alphanumeric-only before hashing, so formatting differences (spaces,
     * case, punctuation) don't produce false negatives on duplicate detection.
     *
     * Known, accepted limitation: this leaks equality — someone with database
     * access can tell two profiles share a passport number, though never the
     * number itself. Accepted per DataCredential.txt sign-off.
     */
    public static function hashPassportNumber(string $number): string
    {
        $normalised = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $number));

        return hash_hmac('sha256', $normalised, (string) config('app.blind_index_key'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function nationalityCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_country_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function passportIssuingCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'passport_issuing_country_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function residenceCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'residence_country_id');
    }
}
