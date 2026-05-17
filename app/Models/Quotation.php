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
use Illuminate\Support\Collection;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'quotation_date',
        'expiry_date',
        'customer_id',
        'customer_confirmation_id',
        'country_id',
        'branch_id',
        'handled_by',
        'description',
        'payment_plan',
        'extensions',
        'status',
        'reason',
        'is_locked',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'extensions' => 'array',
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

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
        return $this->extensionsCollection()->reduce(function (int $sum, array $extension): int {
            return $sum + (int) round(((float) ($extension['amount'] ?? 0)) * 100);
        }, 0);
    }

    public function extensionsCollection(): Collection
    {
        return collect(is_array($this->extensions) ? $this->extensions : [])
            ->filter(fn ($extension) => is_array($extension))
            ->values();
    }

    private function computeItemTaxTotalCents(): int
    {
        return $this->quotationItems->reduce(
            function (int $sum, QuotationItem $item): int {
                if ($item->is_header) {
                    return $sum;
                }

                $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);

                $taxCents = $item->taxes->sum(function ($tax) use ($lineAmount): int {
                    $mode = strtolower((string) ($tax->calculation_mode ?? ''));
                    $value = (float) ($tax->calculation_value ?? 0);

                    if (! in_array($mode, ['fixed', 'percentage'], true)) {
                        return 0;
                    }

                    $taxAmount = $mode === 'percentage'
                        ? ($lineAmount * $value / 100)
                        : $value;

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
