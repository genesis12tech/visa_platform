<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLocation extends Model
{
    use HasUlid;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'country_id',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'timezone',
        'latitude',
        'longitude',
        'operating_hours',
        'contact_phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'operating_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
