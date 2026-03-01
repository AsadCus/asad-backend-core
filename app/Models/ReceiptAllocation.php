<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptAllocation extends Model
{
    protected $fillable = [
        'receipt_id',
        'customer_confirmation_member_id',
        'allocated_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function confirmationMember(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmationMember::class, 'customer_confirmation_member_id');
    }
}
