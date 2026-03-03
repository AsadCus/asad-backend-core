<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        if (Invoice::query()->exists()) {
            $this->command->info('Invoices already seeded, skipping...');

            return;
        }

        $orders = Order::query()
            ->with(['invoices', 'quotation.quotationItems'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->command->warn('No orders found for invoice seeding.');

            return;
        }

        foreach ($orders as $index => $order) {
            if ($order->invoices->isNotEmpty()) {
                continue;
            }

            $quotationItems = $order->quotation?->quotationItems ?? collect();

            $amount = (float) $quotationItems->sum(function ($item) {
                if ($item->is_header) {
                    return 0;
                }

                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

            if ($amount <= 0) {
                continue;
            }

            $invoiceDate = Carbon::parse($order->quotation?->quotation_date ?? now())
                ->addDays(3 + min($index, 8));

            $itemIds = $quotationItems
                ->where('is_header', false)
                ->pluck('id')
                ->values();

            $paymentPlan = (string) ($order->payment_plan ?? 'full');

            if ($paymentPlan === 'installment') {
                $depositAmount = round($amount * 0.4, 2);
                $balanceAmount = round($amount - $depositAmount, 2);

                $depositInvoice = Invoice::query()->create([
                    'order_id' => $order->id,
                    'type' => 'deposit',
                    'description' => 'Seeded deposit invoice from quotation '.$order->quotation?->quotation_number,
                    'amount' => $depositAmount,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $invoiceDate->copy()->addDays(7)->toDateString(),
                    'status' => 'issued',
                ]);

                $balanceInvoice = Invoice::query()->create([
                    'order_id' => $order->id,
                    'type' => 'handover',
                    'description' => 'Seeded balance invoice from quotation '.$order->quotation?->quotation_number,
                    'amount' => $balanceAmount,
                    'invoice_date' => $invoiceDate->copy()->addDays(10)->toDateString(),
                    'due_date' => $invoiceDate->copy()->addDays(24)->toDateString(),
                    'status' => 'issued',
                ]);

                if ($itemIds->isNotEmpty()) {
                    $syncIds = $itemIds->all();
                    $depositInvoice->quotationItems()->sync($syncIds);
                    $balanceInvoice->quotationItems()->sync($syncIds);
                }

                continue;
            }

            $invoice = Invoice::query()->create([
                'order_id' => $order->id,
                'type' => 'deposit',
                'description' => 'Seeded invoice from quotation '.$order->quotation?->quotation_number,
                'amount' => round($amount, 2),
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays(14)->toDateString(),
                'status' => 'issued',
            ]);

            if ($itemIds->isNotEmpty()) {
                $invoice->quotationItems()->sync($itemIds->all());
            }
        }

        $this->command->info('Invoices seeded successfully.');
    }
}
