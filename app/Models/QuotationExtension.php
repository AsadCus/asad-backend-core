<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationExtension extends Model
{
    protected $fillable = [
        'quotation_id',
        'name',
        'type',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quotation_id' => 'integer',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
