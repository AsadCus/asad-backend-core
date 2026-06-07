<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Helpers\NumberGenerator;
use App\Services\FinancialTransactionService;
use App\Services\PaymentStatusService;
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
        'refund_to',
        'reference',
        'description',
        'email_sent_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receipt_date' => 'date',
        'email_sent_at' => 'datetime',
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

        static::created(function ($receipt) {
            $invoice = $receipt->invoice;

            if (! $invoice) {
                return;
            }

            // Check if quotation is cancelled or soft deleted - don't create financial transaction
            $quotation = $invoice->order?->quotation;
            if (! ($quotation && ($quotation->status === QuotationStatus::Cancelled || $quotation->trashed()))) {
                $financialTransactionService = app(FinancialTransactionService::class);

                $financialTransactionService->recordRevenue(
                    amount: (float) $receipt->amount,
                    description: "Payment received - Invoice #{$invoice->invoice_number}",
                    date: Carbon::parse($receipt->receipt_date),
                    referenceType: 'App\Models\Receipt',
                    referenceId: $receipt->id,
                    metadata: [
                        'receipt_number' => $receipt->receipt_number,
                        'invoice_number' => $invoice->invoice_number,
                        'payment_method' => $receipt->payment_method,
                        'reference' => $receipt->reference,
                    ]
                );
            }

            app(PaymentStatusService::class)
                ->syncAfterReceiptMutation((int) $receipt->invoice_id);
        });

        static::updated(function ($receipt) {
            if ($receipt->wasChanged(['invoice_id', 'amount', 'receipt_date'])) {
                $financialTransactionService = app(FinancialTransactionService::class);
                $financialTransactionService->updateReceiptRevenue($receipt);

                app(PaymentStatusService::class)
                    ->syncAfterReceiptReassignment(
                        $receipt->getOriginal('invoice_id')
                            ? (int) $receipt->getOriginal('invoice_id')
                            : null,
                        $receipt->invoice_id
                            ? (int) $receipt->invoice_id
                            : null,
                    );
            }
        });

        static::deleting(function ($receipt) {
            FinancialTransaction::where('reference_type', 'App\Models\Receipt')->where('reference_id', $receipt->id)->delete();
        });

        static::deleted(function ($receipt) {
            if ($receipt->invoice_id) {
                app(PaymentStatusService::class)
                    ->syncAfterReceiptMutation((int) $receipt->invoice_id);
            }
        });
    }
}
