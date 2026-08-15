<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaTypeDocumentRequirement extends Model
{
    protected $attributes = [
        'is_required' => true,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'visa_type_id',
        'document_type_id',
        'is_required',
        'condition_rules',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'condition_rules' => 'array',
            'sort_order' => 'integer',
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
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
