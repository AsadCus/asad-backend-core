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
        'commencement_date',
        'customer_id',
        'description',
        'monthly_salary',
        'loan_duration',
        'rest_day_of_the_week',
        'rest_days_per_month',
        'compensation_off_in_lieu',
        'payment_plan',
        'payment_method',
        'status',
        'reason',
        'is_locked',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'commencement_date' => 'date',
        'monthly_salary' => 'decimal:2',
        'loan_duration' => 'decimal:2',
        'compensation_off_in_lieu' => 'decimal:2',
        'is_locked' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function totalPlacementFee(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalCents = $this->quotationItems
                    ->where('is_placement_fee', true)
                    ->where('is_header', false)
                    ->reduce(function (int $sum, $item) {
                        $qty = (int) round(((float) ($item->quantity ?? 0)) * 100);
                        $rate = (int) round(((float) ($item->rate ?? 0)) * 100);

                        return $sum + (int) round(($qty / 100) * $rate);
                    }, 0);

                return $totalCents / 100;
            }
        );
    }

    public function totalPlacementQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $qtyCents = $this->quotationItems
                    ->where('is_placement_fee', true)
                    ->where('is_header', false)
                    ->reduce(function (int $sum, $item) {
                        return $sum + (int) round(((float) ($item->quantity ?? 0)) * 100);
                    }, 0);

                return $qtyCents / 100;
            }
        );
    }

    public function monthlyPlacementFee(): Attribute
    {
        return Attribute::make(
            get: function () {
                $qty = $this->total_placement_quantity;

                if ($qty <= 0) {
                    return 0;
                }

                return round($this->total_placement_fee / $qty, 2);
            }
        );
    }

    public function getCanGenerateOrderAttribute(): bool
    {
        return $this->status === 'accepted' && ! $this->is_locked;
    }

    public function placementFeeInvoices(): Attribute
    {
        return Attribute::make(
            get: function () {
                $invoices = $this->order?->invoices
                    ?->load('quotationItems')
                    ->filter(function ($invoice) {
                        return $invoice->quotationItems->contains(
                            fn ($item) => (bool) $item->is_placement_fee
                        );
                    }) ?? collect();

                return $invoices
                    ->sortBy('due_date')
                    ->values()
                    ->map(function ($invoice) {
                        $placementItems = $invoice->quotationItems
                            ->where('is_placement_fee', true)
                            ->where('is_header', false);

                        $placementAmount = $placementItems->reduce(
                            function (float $sum, $item) {
                                return $sum
                                    + ((float) ($item->quantity ?? 0)
                                        * (float) ($item->rate ?? 0));
                            },
                            0.0
                        );

                        return [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'due_date' => $invoice->due_date_formatted,
                            'amount' => round(
                                $placementAmount > 0
                                    ? $placementAmount
                                    : (float) ($invoice->amount ?? 0),
                                2
                            ),
                            'description' => $invoice->description ?? '',
                        ];
                    })
                    ->all();
            }
        );
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

    public function getCommencementDateFormattedAttribute(): ?string
    {
        return $this->commencement_date
            ? Carbon::parse($this->commencement_date)->translatedFormat('d F Y')
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
