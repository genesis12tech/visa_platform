<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Reference table — never addressed publicly, so no ulid column
 * (Backend_schema.md §4.3, D-2).
 */
class RejectionReason extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'scope',
        'code',
        'label',
        'applicant_text',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
