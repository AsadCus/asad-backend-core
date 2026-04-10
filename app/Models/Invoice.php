<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use App\Support\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'order_id',
        'invoice_number',
        'description',
        'payment_method',
        'extensions',
        'amount',
        'invoice_date',
        'due_date',
        'status',
    ];

    protected $casts = [
        'extensions' => 'array',
        'amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function quotationItems(): BelongsToMany
    {
        return $this->belongsToMany(QuotationItem::class, 'invoice_items');
    }

    public function receipt(): HasMany
    {
        return $this->hasMany(Receipt::class, 'invoice_id');
    }

    public function invoiceNotes(): HasMany
    {
        return $this->hasMany(InvoiceNotes::class);
    }

    public function outstandingAmount(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalPaid = $this->receipt->sum('amount');

                return max(0, (float) $this->amount - (float) $totalPaid);
            }
        );
    }

    // Formatting Helpers
    public function getInvoiceDateFormattedAttribute(): ?string
    {
        return $this->invoice_date
            ? Carbon::parse($this->invoice_date)->translatedFormat('d F Y')
            : null;
    }

    public function getDueDateFormattedAttribute(): ?string
    {
        return $this->due_date
            ? Carbon::parse($this->due_date)->translatedFormat('d F Y')
            : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (InvoiceStatus::isRefund($invoice->status ?? null)) {
                $invoice->invoice_number = null;

                return;
            }

            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = NumberGenerator::generate('invoice');
            }
        });
    }

    public function isRefund(): bool
    {
        return InvoiceStatus::isRefund($this->status);
    }
}
