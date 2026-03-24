<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationExtension extends Model
{
    protected $fillable = [
        'quotation_id',
        'quotation_extension_master_id',
        'name',
        'type',
        'calculation_mode',
        'calculation_value',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quotation_id' => 'integer',
            'quotation_extension_master_id' => 'integer',
            'calculation_value' => 'decimal:4',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function extensionMaster(): BelongsTo
    {
        return $this->belongsTo(QuotationExtensionMaster::class, 'quotation_extension_master_id');
    }
}
