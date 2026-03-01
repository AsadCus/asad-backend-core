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

            $invoice = Invoice::query()->create([
                'order_id' => $order->id,
                'type' => 'deposit',
                'description' => 'Seeded invoice from quotation '.$order->quotation?->quotation_number,
                'amount' => round($amount, 2),
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays(14)->toDateString(),
                'status' => 'issued',
            ]);

            $itemIds = $quotationItems
                ->where('is_header', false)
                ->pluck('id')
                ->values();

            if ($itemIds->isNotEmpty()) {
                $invoice->quotationItems()->sync($itemIds->all());
            }
        }

        $this->command->info('Invoices seeded successfully.');
    }
}
