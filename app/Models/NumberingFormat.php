<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingFormat extends Model
{
    protected $fillable = [
        'model_key',
        'name',
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
            'increment_padding' => 'integer',
            'increment_start' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
