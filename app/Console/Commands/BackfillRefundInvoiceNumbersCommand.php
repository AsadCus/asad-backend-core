<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\NumberingService;
use App\Support\InvoiceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BackfillRefundInvoiceNumbersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:backfill-refund-invoice-numbers {--dry-run : Preview changes without writing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill refund invoices to null invoice numbers and preserve forward invoice numbering';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $refundInvoiceQuery = Invoice::query()
            ->where('status', InvoiceStatus::Refund)
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '');

        $affectedCount = (clone $refundInvoiceQuery)->count();

        if ($affectedCount === 0) {
            $this->info('No refund invoices with invoice numbers were found.');

            return self::SUCCESS;
        }

        $latestInvoiceNumberBeforeBackfill = Invoice::query()
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->orderByDesc('id')
            ->value('invoice_number');

        if ($isDryRun) {
            $this->info('Dry run completed.');
            $this->line('Refund invoices to update: '.$affectedCount);
            $this->line('Latest invoice number before backfill: '.($latestInvoiceNumberBeforeBackfill ?: '-'));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($refundInvoiceQuery): void {
            $refundInvoiceQuery
                ->chunkById(100, function ($invoices): void {
                    foreach ($invoices as $invoice) {
                        $invoice->update([
                            'invoice_number' => null,
                        ]);
                    }
                });
        });

        if (is_string($latestInvoiceNumberBeforeBackfill) && trim($latestInvoiceNumberBeforeBackfill) !== '') {
            try {
                app(NumberingService::class)->updateSimpleLatestNumber('invoice', trim($latestInvoiceNumberBeforeBackfill));
            } catch (ValidationException) {
                $this->warn('Could not sync invoice simple counter from latest number.');
            }
        }

        $this->info('Backfill completed.');
        $this->line('Refund invoices updated: '.$affectedCount);
        $this->line('Preserved latest invoice number: '.($latestInvoiceNumberBeforeBackfill ?: '-'));

        return self::SUCCESS;
    }
}
