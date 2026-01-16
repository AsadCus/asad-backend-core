<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Order extends Model
{
    protected $fillable = [
        'quotation_id',
        'order_number',
        'payment_plan',
        'handover_date',
    ];

    protected $casts = [
        'handover_date' => 'date',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id');
    }

    // Formatting Helpers
    public function getHandoverDateFormattedAttribute(): ?string
    {
        return $this->handover_date
            ? Carbon::parse($this->handover_date)->translatedFormat('d F Y')
            : null;
    }

    // Model Hooks
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = NumberGenerator::generate('order');
            }
        });
    }
}
