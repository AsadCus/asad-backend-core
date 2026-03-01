<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'quotation_date',
        'expiry_date',
        'customer_id',
        'customer_confirmation_id',
        'description',
        'payment_plan',
        'deposit_type',
        'deposit_value',
        'payment_method',
        'status',
        'reason',
        'is_locked',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'deposit_value' => 'decimal:2',
        'is_locked' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerConfirmation(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmation::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function quotationNotes(): HasMany
    {
        return $this->hasMany(QuotationNotes::class);
    }

    // Computed Attributes
    public function totalAmount(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalCents = $this->quotationItems->reduce(
                    function (int $sum, $item) {
                        if ($item->is_header) {
                            return $sum;
                        }

                        $quantity = (float) ($item->quantity ?? 0);
                        $rate = (float) ($item->rate ?? 0);

                        $amountCents = (int) round($quantity * $rate * 100);

                        return $sum + $amountCents;
                    },
                    0
                );

                return $totalCents / 100;
            }
        );
    }

    public function getCanGenerateOrderAttribute(): bool
    {
        return $this->status === 'accepted' && ! $this->is_locked;
    }

    public function salesRegistrationNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                return auth()->user()->sales ? auth()->user()->sales->registration_number : null;
            }
        );
    }

    // Formatting Helpers
    public function getQuotationDateFormattedAttribute(): ?string
    {
        return $this->quotation_date
            ? Carbon::parse($this->quotation_date)->translatedFormat('d F Y')
            : null;
    }

    public function getExpiryDateFormattedAttribute(): ?string
    {
        return $this->expiry_date
            ? Carbon::parse($this->expiry_date)->translatedFormat('d F Y')
            : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quotation) {
            if (empty($quotation->quotation_number)) {
                $quotation->quotation_number = NumberGenerator::generate('quotation');
            }
        });

        static::deleting(function ($quotation) {
            $order = $quotation->order;
            if ($order) {
                foreach ($order->invoices as $invoice) {
                    foreach ($invoice->receipt as $receipt) {
                        FinancialTransaction::where('reference_type', 'App\Models\Receipt')->where('reference_id', $receipt->id)->delete();
                    }
                }
            }
        });

        static::restored(function ($quotation) {
            if ($quotation->status === 'cancelled') {
                return;
            }

            $order = $quotation->order;
            if ($order) {
                foreach ($order->invoices as $invoice) {
                    foreach ($invoice->receipt as $receipt) {
                        FinancialTransaction::withTrashed()->where('reference_type', 'App\Models\Receipt')->where('reference_id', $receipt->id)->restore();
                    }
                }
            }
        });
    }
}
