<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodMaster extends Model
{
    protected $fillable = [
        'name',
        'value',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
