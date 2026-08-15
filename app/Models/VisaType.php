<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaType extends Model
{
    use HasUlid;

    protected $attributes = [
        'sla_business_hours' => 240,
        'requires_biometrics' => false,
        'requires_interview' => false,
        'requires_four_eyes' => false,
        'max_reschedules' => 2,
        'is_active' => false,
    ];

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'description',
        'processing_days_min',
        'processing_days_max',
        'sla_business_hours',
        'requires_biometrics',
        'requires_interview',
        'requires_four_eyes',
        'max_reschedules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'processing_days_min' => 'integer',
            'processing_days_max' => 'integer',
            'sla_business_hours' => 'integer',
            'requires_biometrics' => 'boolean',
            'requires_interview' => 'boolean',
            'requires_four_eyes' => 'boolean',
            'max_reschedules' => 'integer',
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
