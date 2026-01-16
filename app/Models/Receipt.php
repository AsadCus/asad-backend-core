<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    protected $fillable = [
        'invoice_id',
        'receipt_number',
        'amount',
        'receipt_date',
        'payment_method',
        'reference',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receipt_date' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function receiptNotes(): HasMany
    {
        return $this->hasMany(ReceiptNotes::class);
    }

    // Formatting Helpers
    public function getReceiptDateFormattedAttribute(): ?string
    {
        return $this->receipt_date
            ? Carbon::parse($this->receipt_date)->translatedFormat('d F Y')
            : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            if (empty($receipt->receipt_number)) {
                $receipt->receipt_number = NumberGenerator::generate('receipt');
            }
        });
    }
}
