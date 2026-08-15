<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasUlid;

    protected $attributes = [
        'max_size_bytes' => 10_485_760,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'allowed_mime_types',
        'allowed_extensions',
        'max_size_bytes',
        'max_pages',
        'min_width_px',
        'min_height_px',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allowed_mime_types' => 'array',
            'allowed_extensions' => 'array',
            'max_size_bytes' => 'integer',
            'max_pages' => 'integer',
            'min_width_px' => 'integer',
            'min_height_px' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
