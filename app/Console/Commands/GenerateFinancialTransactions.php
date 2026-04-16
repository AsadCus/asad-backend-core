<?php

namespace App\Console\Commands;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Receipt;
use App\Services\FinancialTransactionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateFinancialTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financial:generate-transactions {--year= : Specific financial year ID to process} {--all : Process all financial years}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate financial transactions from paid invoices';

    protected $financialTransactionService;

    public function __construct(FinancialTransactionService $financialTransactionService)
    {
        parent::__construct();
        $this->financialTransactionService = $financialTransactionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting financial transaction generation...');

        $this->cleanupOrphanedTransactions();

        FinancialYear::progressFinancialYear();

        $yearIdOption = $this->option('year');
        $targetYearId = $yearIdOption !== null ? (int) $yearIdOption : null;
        $processAll = (bool) $this->option('all') || ! $targetYearId;

        if ($targetYearId !== null && ! FinancialYear::query()->whereKey($targetYearId)->exists()) {
            $this->error('Target financial year not found.');

            return 1;
        }

        $this->generateTransactions($targetYearId, $processAll);

        $this->info("\n✓ Financial transaction generation completed!");

        return 0;
    }

    /**
     * Clean up orphaned receipt financial transactions.
     */
    protected function cleanupOrphanedTransactions()
    {
        $this->info('Cleaning up orphaned transactions...');

        $orphanedReceiptTransactions = FinancialTransaction::where('reference_type', 'App\Models\Receipt')
            ->whereNotNull('reference_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('receipts')
                    ->whereColumn('receipts.id', 'financial_transactions.reference_id');
            })
            ->count();

        if ($orphanedReceiptTransactions > 0) {
            FinancialTransaction::where('reference_type', 'App\Models\Receipt')
                ->whereNotNull('reference_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('receipts')
                        ->whereColumn('receipts.id', 'financial_transactions.reference_id');
                })
                ->delete();
            $this->info("  ✓ Deleted {$orphanedReceiptTransactions} orphaned receipt transactions");
        }

        $cancelledQuotationTransactions = FinancialTransaction::where('reference_type', 'App\Models\Receipt')
            ->whereNotNull('reference_id')
            ->whereNull('deleted_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('receipts')
                    ->join('invoices', 'invoices.id', '=', 'receipts.invoice_id')
                    ->join('orders', 'orders.id', '=', 'invoices.order_id')
                    ->join('quotations', 'quotations.id', '=', 'orders.quotation_id')
                    ->whereColumn('receipts.id', 'financial_transactions.reference_id')
                    ->where(function ($q) {
                        $q->where('quotations.status', 'cancelled')
                            ->orWhereNotNull('quotations.deleted_at');
                    });
            })->count();

        if ($cancelledQuotationTransactions > 0) {
            FinancialTransaction::where('reference_type', 'App\Models\Receipt')
                ->whereNotNull('reference_id')
                ->whereNull('deleted_at')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('receipts')
                        ->join('invoices', 'invoices.id', '=', 'receipts.invoice_id')
                        ->join('orders', 'orders.id', '=', 'invoices.order_id')
                        ->join('quotations', 'quotations.id', '=', 'orders.quotation_id')
                        ->whereColumn('receipts.id', 'financial_transactions.reference_id')
                        ->where(function ($q) {
                            $q->where('quotations.status', 'cancelled')
                                ->orWhereNotNull('quotations.deleted_at');
                        });
                })->delete();
            $this->info("  ✓ Soft deleted {$cancelledQuotationTransactions} transactions for cancelled/deleted quotations");
        }

        if ($orphanedReceiptTransactions == 0 && $cancelledQuotationTransactions == 0) {
            $this->info('  ✓ No orphaned transactions found');
        }
    }

    protected function generateTransactions(?int $targetYearId, bool $processAll): void
    {
        $this->info($processAll
            ? 'Processing all receipts...'
            : "Processing receipts for financial year ID {$targetYearId}...");

        $receipts = Receipt::query()
            ->with(['invoice.order.quotation'])
            ->get();

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $receiptBar = $this->output->createProgressBar($receipts->count());

        foreach ($receipts as $receipt) {
            $invoice = $receipt->invoice;

            if (! $invoice) {
                $skippedCount++;
                $receiptBar->advance();

                continue;
            }

            $quotation = $invoice->order?->quotation;
            $shouldBeSoftDeleted = $quotation &&
                (in_array((string) $quotation->status, ['cancelled', 'rejected', 'expired'], true)
                    || $quotation->deleted_at !== null);

            $resolvedYear = $this->financialTransactionService->resolveFinancialYearForDate(
                Carbon::parse($receipt->receipt_date),
            );

            $existingTransaction = FinancialTransaction::withTrashed()
                ->where('reference_type', Receipt::class)
                ->where('reference_id', $receipt->id)
                ->first();

            $belongsToTarget = $processAll || (
                ($resolvedYear && (int) $resolvedYear->id === $targetYearId)
                || ($existingTransaction && (int) $existingTransaction->financial_year_id === $targetYearId)
            );

            if (! $belongsToTarget) {
                $skippedCount++;
                $receiptBar->advance();

                continue;
            }

            if (! $resolvedYear && ! $existingTransaction) {
                $skippedCount++;
                $receiptBar->advance();

                continue;
            }

            $metadata = [
                'receipt_number' => $receipt->receipt_number,
                'invoice_number' => $invoice->invoice_number,
                'payment_method' => $receipt->payment_method,
                'reference' => $receipt->reference,
            ];

            if (! $existingTransaction) {
                try {
                    $transaction = $this->financialTransactionService->recordRevenue(
                        (float) $receipt->amount,
                        "Payment received: {$invoice->invoice_number}",
                        Carbon::parse($receipt->receipt_date),
                        Receipt::class,
                        $receipt->id,
                        $metadata
                    );

                    if ($shouldBeSoftDeleted) {
                        $transaction->delete();
                    }

                    $createdCount++;
                } catch (\Exception $e) {
                    $this->error("\nError processing receipt {$receipt->id}: {$e->getMessage()}");
                    $skippedCount++;
                }
            } else {
                $existingTransaction->update([
                    'financial_year_id' => $resolvedYear
                        ? (int) $resolvedYear->id
                        : (int) $existingTransaction->financial_year_id,
                    'type' => 'revenue',
                    'amount' => (float) $receipt->amount,
                    'description' => "Payment received: {$invoice->invoice_number}",
                    'transaction_date' => Carbon::parse($receipt->receipt_date)->toDateString(),
                    'metadata' => $metadata,
                ]);

                if ($shouldBeSoftDeleted && $existingTransaction->deleted_at === null) {
                    $existingTransaction->delete();
                } elseif (! $shouldBeSoftDeleted && $existingTransaction->deleted_at !== null) {
                    $existingTransaction->restore();
                }

                $updatedCount++;
            }

            $receiptBar->advance();
        }

        $receiptBar->finish();
        $this->info("\n  ✓ Created {$createdCount}, updated {$updatedCount}, skipped {$skippedCount} receipt transactions");
    }
}
