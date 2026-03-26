<?php

namespace App\Models;

use App\Enums\QuotationStatus;
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
        'payment_method',
        'status',
        'reason',
        'is_locked',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'is_locked' => 'boolean',
        'status' => QuotationStatus::class,
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

    public function quotationExtensions(): HasMany
    {
        return $this->hasMany(QuotationExtension::class);
    }

    // Computed Attributes
    public function itemSubtotalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->computeItemSubtotalCents() / 100
        );
    }

    public function extensionTotalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->computeItemTaxTotalCents() + $this->computeExtensionTotalCents()) / 100
        );
    }

    public function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->computeItemSubtotalCents() + $this->computeItemTaxTotalCents() + $this->computeExtensionTotalCents()) / 100
        );
    }

    private function computeItemSubtotalCents(): int
    {
        return $this->quotationItems->reduce(
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
    }

    private function computeExtensionTotalCents(): int
    {
        return $this->quotationExtensions->reduce(
            fn (int $sum, QuotationExtension $extension) => $sum + (int) round(((float) $extension->amount) * 100),
            0
        );
    }

    private function computeItemTaxTotalCents(): int
    {
        return $this->quotationItems->reduce(
            function (int $sum, QuotationItem $item): int {
                if ($item->is_header) {
                    return $sum;
                }

                $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);

                $taxCents = $item->taxes->sum(function (QuotationItemTax $tax) use ($lineAmount): int {
                    $calculationMode = (string) ($tax->calculation_mode ?? '');
                    $calculationValue = (float) ($tax->calculation_value ?? 0);

                    if (! in_array($calculationMode, ['fixed', 'percentage'], true) || $calculationValue <= 0) {
                        return 0;
                    }

                    $taxAmount = $calculationMode === 'percentage'
                        ? ($lineAmount * $calculationValue / 100)
                        : $calculationValue;

                    return (int) round($taxAmount * 100);
                });

                return $sum + (int) $taxCents;
            },
            0
        );
    }

    public function getCanGenerateOrderAttribute(): bool
    {
        return $this->status === QuotationStatus::Accepted && ! $this->is_locked;
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
            if ($quotation->status === QuotationStatus::Cancelled) {
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
