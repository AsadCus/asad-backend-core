<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationExtensionMaster extends Model
{
    protected $fillable = [
        'name',
        'type',
        'calculation_mode',
        'calculation_value',
        'payment_methods',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'calculation_value' => 'decimal:4',
            'payment_methods' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
