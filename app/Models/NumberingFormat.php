<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberingFormat extends Model
{
    protected $fillable = [
        'model_key',
        'name',
        'prefix',
        'separator',
        'include_year',
        'year_format',
        'increment_padding',
        'increment_start',
        'increment_scope',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'include_year' => 'boolean',
            'increment_padding' => 'integer',
            'increment_start' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(NumberingSequence::class, 'numbering_format_id');
    }
}
