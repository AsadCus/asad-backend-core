<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\PaymentStatusService;
use App\Support\InvoiceStatus;
use Illuminate\Console\Command;

class NormalizeReceiptInvoiceStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receipts:normalize-invoice-status {--dry-run : Preview changes without writing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize receipt totals to invoice totals and re-sync invoice payment statuses';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $paymentStatusService = app(PaymentStatusService::class);

        $updatedReceiptRows = 0;
        $normalizedInvoices = 0;
        $skippedCancelledOrRefundInvoices = 0;

        Invoice::query()
            ->with(['receipt' => function ($query) {
                $query->orderBy('id');
            }])
            ->whereHas('receipt')
            ->chunkById(100, function ($invoices) use (
                $isDryRun,
                $paymentStatusService,
                &$updatedReceiptRows,
                &$normalizedInvoices,
                &$skippedCancelledOrRefundInvoices
            ) {
                foreach ($invoices as $invoice) {
                    if (
                        InvoiceStatus::isRefund($invoice->status)
                        || strtolower(trim((string) $invoice->status)) === InvoiceStatus::Cancelled
                    ) {
                        $skippedCancelledOrRefundInvoices++;

                        continue;
                    }

                    $receipts = $invoice->receipt->values();

                    if ($receipts->isEmpty()) {
                        continue;
                    }

                    $invoiceAmount = round((float) ($invoice->amount ?? 0), 2);
                    $receiptUpdates = [];

                    if ($receipts->count() === 1) {
                        $currentAmount = round((float) ($receipts[0]->amount ?? 0), 2);

                        if ($currentAmount !== $invoiceAmount) {
                            $receiptUpdates[] = [
                                'id' => (int) $receipts[0]->id,
                                'amount' => $invoiceAmount,
                            ];
                        }
                    } else {
                        foreach ($receipts as $index => $receipt) {
                            $targetAmount = $index === 0 ? $invoiceAmount : 0.0;
                            $currentAmount = round((float) ($receipt->amount ?? 0), 2);

                            if ($currentAmount !== $targetAmount) {
                                $receiptUpdates[] = [
                                    'id' => (int) $receipt->id,
                                    'amount' => $targetAmount,
                                ];
                            }
                        }
                    }

                    if (empty($receiptUpdates)) {
                        continue;
                    }

                    $normalizedInvoices++;
                    $updatedReceiptRows += count($receiptUpdates);

                    if ($isDryRun) {
                        continue;
                    }

                    foreach ($receiptUpdates as $update) {
                        $receipt = Receipt::query()->find($update['id']);

                        if (! $receipt) {
                            continue;
                        }

                        $receipt->update([
                            'amount' => $update['amount'],
                        ]);
                    }

                    $paymentStatusService->syncAfterReceiptMutation((int) $invoice->id);
                }
            });

        $this->info($isDryRun ? 'Dry run completed.' : 'Normalization completed.');
        $this->line('Normalized invoices: '.$normalizedInvoices);
        $this->line('Updated receipt rows: '.$updatedReceiptRows);
        $this->line('Skipped cancelled/refund invoices: '.$skippedCancelledOrRefundInvoices);

        return self::SUCCESS;
    }
}
