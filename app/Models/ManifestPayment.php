<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestPayment extends Model
{
    protected $fillable = [
        'manifest_id',
        'traveler_name',
        'description',
        'amount',
        'paid_amount',
        'outstanding_amount',
        'payment_date',
        'status',
        'manifest_traveler_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function getPaymentDateFormattedAttribute(): ?string
    {
        return $this->payment_date?->format('d/m/Y');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(ManifestTraveler::class, 'manifest_traveler_id');
    }
}
