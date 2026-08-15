<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reference table — never addressed publicly, so no ulid column
 * (Backend_schema.md §4.3, D-2).
 */
class Country extends Model
{
    public $timestamps = false;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'iso2',
        'iso3',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $country) {
            $country->iso2 = strtoupper($country->iso2);
            $country->iso3 = strtoupper($country->iso3);
        });
    }
}
