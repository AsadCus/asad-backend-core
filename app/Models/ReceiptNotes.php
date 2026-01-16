<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptNotes extends Model
{
    protected $fillable = [
        'receipt_id',
        'description',
        'sort_order',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
