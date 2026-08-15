<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaFee extends Model
{
    use HasUlid;

    protected $attributes = [
        'service_fee_minor' => 0,
        'priority_fee_minor' => 0,
        'tax_minor' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'visa_type_id',
        'nationality_country_id',
        'currency',
        'base_fee_minor',
        'service_fee_minor',
        'priority_fee_minor',
        'tax_minor',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_fee_minor' => MoneyCast::class.':currency',
            'service_fee_minor' => MoneyCast::class.':currency',
            'priority_fee_minor' => MoneyCast::class.':currency',
            'tax_minor' => MoneyCast::class.':currency',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<VisaType, $this>
     */
    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function nationalityCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_country_id');
    }
}
