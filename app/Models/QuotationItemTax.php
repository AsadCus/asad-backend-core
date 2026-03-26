<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItemTax extends Model
{
    protected $fillable = [
        'quotation_item_id',
        'quotation_extension_master_id',
        'name',
        'calculation_mode',
        'calculation_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quotation_item_id' => 'integer',
            'quotation_extension_master_id' => 'integer',
            'calculation_value' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
    }

    public function extensionMaster(): BelongsTo
    {
        return $this->belongsTo(QuotationExtensionMaster::class, 'quotation_extension_master_id');
    }
}
