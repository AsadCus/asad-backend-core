<?php

namespace App\Console\Commands;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Maid;
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
    protected $description = 'Generate financial transactions from existing maids and paid invoices';

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

        $this->ensureFinancialYearsExist();

        $yearId = $this->option('year');
        $processAll = $this->option('all');

        if (! $yearId && ! $processAll) {
            $this->error('Please specify either --year=ID or --all option');

            return 1;
        }

        $financialYears = $processAll
            ? FinancialYear::where('is_active', true)->get()
            : FinancialYear::where('id', $yearId)->get();

        if ($financialYears->isEmpty()) {
            $this->error('No financial years found');

            return 1;
        }

        foreach ($financialYears as $year) {
            $this->info("\nProcessing Financial Year: {$year->year}");
            $this->generateTransactionsForYear($year);
        }

        $this->info("\n✓ Financial transaction generation completed!");

        return 0;
    }

    /**
     * Ensure financial years exist for all maids and receipts
     * Creates missing financial years automatically
     */
    protected function ensureFinancialYearsExist(): void
    {
        $this->info('Checking for missing financial years...');

        $maidDates = Maid::selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')->first();

        $receiptDates = Receipt::selectRaw('MIN(receipt_date) as min_date, MAX(receipt_date) as max_date')->first();

        $dates = collect([
            $maidDates->min_date ? Carbon::parse($maidDates->min_date) : null,
            $maidDates->max_date ? Carbon::parse($maidDates->max_date) : null,
            $receiptDates->min_date ? Carbon::parse($receiptDates->min_date) : null,
            $receiptDates->max_date ? Carbon::parse($receiptDates->max_date) : null,
        ])->filter();

        if ($dates->isEmpty()) {
            $this->info('  ✓ No data found, skipping financial year creation');

            return;
        }

        $minDate = $dates->min();
        $maxDate = $dates->max();

        $createdCount = 0;
        $currentDate = $minDate->copy();

        while ($currentDate->lte($maxDate)) {
            $fyYear = $this->getFiscalYear($currentDate);
            $created = $this->createFinancialYearIfNotExists($fyYear);
            if ($created) {
                $createdCount++;
            }
            $currentDate = Carbon::create($fyYear, 10, 28);
        }

        if ($createdCount > 0) {
            $this->info("  ✓ Created {$createdCount} missing financial year(s)");
        } else {
            $this->info('  ✓ All required financial years exist');
        }
    }

    /**
     * Get fiscal year for a given date
     * Uses calendar year (Jan 1 - Dec 31) as default
     */
    protected function getFiscalYear(Carbon $date): int
    {
        return $date->year;
    }

    /**
     * Create a financial year if it doesn't exist
     * Uses calendar year (Jan 1 - Dec 31) as default
     */
    protected function createFinancialYearIfNotExists(int $fyYear): bool
    {
        $existing = FinancialYear::where('year', (string) $fyYear)->first();

        if ($existing) {
            return false;
        }

        // Default to calendar year (Jan 1 - Dec 31)
        $startDate = Carbon::create($fyYear, 1, 1);
        $endDate = Carbon::create($fyYear, 12, 31);

        FinancialYear::create([
            'year' => (string) $fyYear,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'default' => false,
            'is_active' => true,
        ]);

        $this->line("  Created FY {$fyYear}: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        return true;
    }

    /**
     * Clean up orphaned financial transactions that reference deleted maids or receipts
     * Also soft deletes transactions for soft deleted or cancelled quotations
     */
    protected function cleanupOrphanedTransactions()
    {
        $this->info('Cleaning up orphaned transactions...');

        $orphanedMaidTransactions = FinancialTransaction::where('reference_type', 'App\Models\Maid')
            ->whereNotNull('reference_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('maids')
                    ->whereColumn('maids.id', 'financial_transactions.reference_id');
            })
            ->count();

        if ($orphanedMaidTransactions > 0) {
            FinancialTransaction::where('reference_type', 'App\Models\Maid')
                ->whereNotNull('reference_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('maids')
                        ->whereColumn('maids.id', 'financial_transactions.reference_id');
                })
                ->delete();
            $this->info("  ✓ Deleted {$orphanedMaidTransactions} orphaned maid transactions");
        }

        $softDeletedMaidTransactions = FinancialTransaction::where('reference_type', 'App\Models\Maid')
            ->whereNotNull('reference_id')
            ->whereNull('deleted_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('maids')
                    ->whereColumn('maids.id', 'financial_transactions.reference_id')
                    ->whereNotNull('maids.deleted_at');
            })
            ->count();

        if ($softDeletedMaidTransactions > 0) {
            FinancialTransaction::where('reference_type', 'App\Models\Maid')
                ->whereNotNull('reference_id')
                ->whereNull('deleted_at')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('maids')
                        ->whereColumn('maids.id', 'financial_transactions.reference_id')
                        ->whereNotNull('maids.deleted_at');
                })->delete();
            $this->info("  ✓ Soft deleted {$softDeletedMaidTransactions} transactions for soft deleted maids");
        }

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

        if ($orphanedMaidTransactions == 0 && $orphanedReceiptTransactions == 0 && $softDeletedMaidTransactions == 0 && $cancelledQuotationTransactions == 0) {
            $this->info('  ✓ No orphaned transactions found');
        }
    }

    protected function generateTransactionsForYear(FinancialYear $year)
    {
        $startDate = Carbon::parse($year->start_date);
        $endDate = Carbon::parse($year->end_date);

        $this->info('Processing maids...');
        $maids = Maid::withTrashed()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $maidCount = 0;
        $maidSkipped = 0;
        $maidUpdated = 0;
        $maidBar = $this->output->createProgressBar($maids->count());

        foreach ($maids as $maid) {
            $existingTransaction = FinancialTransaction::withTrashed()->where('reference_type', 'App\Models\Maid')->where('reference_id', $maid->id)->first();

            $shouldBeSoftDeleted = $maid->deleted_at !== null;

            if (! $existingTransaction) {
                try {
                    $cost = $maid->getTotalCostOfMaid();

                    $transaction = $this->financialTransactionService->recordExpense(
                        $cost,
                        "Maid acquisition cost: {$maid->name}",
                        Carbon::parse($maid->created_at),
                        'App\Models\Maid',
                        $maid->id,
                        [
                            'maid_number' => $maid->maid_number,
                            'name' => $maid->name,
                            'cost_of_maid' => $maid->cost_of_maid,
                            'commission' => $maid->commission,
                        ]
                    );

                    if ($shouldBeSoftDeleted) {
                        $transaction->delete();
                    }

                    $maidCount++;
                } catch (\Exception $e) {
                    $this->error("\nError processing maid {$maid->id}: {$e->getMessage()}");
                }
            } else {
                if ($shouldBeSoftDeleted && $existingTransaction->deleted_at === null) {
                    $existingTransaction->delete();
                    $maidUpdated++;
                } elseif (! $shouldBeSoftDeleted && $existingTransaction->deleted_at !== null) {
                    $existingTransaction->restore();
                    $maidUpdated++;
                }
                $maidSkipped++;
            }
            $maidBar->advance();
        }
        $maidBar->finish();
        $this->info("\n  ✓ Created {$maidCount} maid expense transactions (skipped {$maidSkipped} existing, updated {$maidUpdated})");

        $this->info('Processing receipts...');

        $receipts = Receipt::whereBetween('receipt_date', [$startDate, $endDate])
            ->whereHas('invoice', function ($query) {
                $query->where('status', 'paid');
            })
            ->with(['invoice.order.quotation'])
            ->get();

        $receiptCount = 0;
        $receiptSkipped = 0;
        $receiptUpdated = 0;
        $receiptBar = $this->output->createProgressBar($receipts->count());

        foreach ($receipts as $receipt) {
            $existingTransaction = FinancialTransaction::withTrashed()->where('reference_type', 'App\Models\Receipt')->where('reference_id', $receipt->id)->first();

            $quotation = $receipt->invoice->order->quotation;
            $shouldBeSoftDeleted = $quotation &&
                ($quotation->status === \App\Enums\QuotationStatus::Cancelled || $quotation->deleted_at !== null);

            if (! $existingTransaction) {
                try {
                    $transaction = $this->financialTransactionService->recordRevenue(
                        (float) $receipt->amount,
                        "Payment received: {$receipt->invoice->invoice_number}",
                        Carbon::parse($receipt->receipt_date),
                        'App\Models\Receipt',
                        $receipt->id,
                        [
                            'receipt_number' => $receipt->receipt_number,
                            'invoice_number' => $receipt->invoice->invoice_number,
                            'payment_method' => $receipt->payment_method,
                            'reference' => $receipt->reference,
                        ]
                    );

                    if ($shouldBeSoftDeleted) {
                        $transaction->delete();
                    }

                    $receiptCount++;
                } catch (\Exception $e) {
                    $this->error("\nError processing receipt {$receipt->id}: {$e->getMessage()}");
                }
            } else {
                if ($shouldBeSoftDeleted && $existingTransaction->deleted_at === null) {
                    $existingTransaction->delete();
                    $receiptUpdated++;
                } elseif (! $shouldBeSoftDeleted && $existingTransaction->deleted_at !== null) {
                    $existingTransaction->restore();
                    $receiptUpdated++;
                }
                $receiptSkipped++;
            }
            $receiptBar->advance();
        }
        $receiptBar->finish();
        $this->info("\n  ✓ Created {$receiptCount} revenue transactions (skipped {$receiptSkipped} existing, updated {$receiptUpdated})");
    }
}
