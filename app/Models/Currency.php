<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Reference table — never addressed publicly, so no ulid column
 * (Backend_schema.md §4.3, D-2).
 */
class Currency extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $attributes = [
        'minor_unit_exponent' => 2,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'minor_unit_exponent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minor_unit_exponent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $currency) {
            $currency->code = strtoupper($currency->code);
        });
    }
}
