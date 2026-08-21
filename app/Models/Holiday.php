<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reference table — never addressed publicly, so no ulid column
 * (Backend_schema.md §4.3, D-2). created_at only, no updated_at: a holiday
 * row is created once and never edited in place.
 */
class Holiday extends Model
{
    use Auditable;

    const UPDATED_AT = null;

    protected $fillable = [
        'location_id',
        'holiday_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ServiceLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }
}
