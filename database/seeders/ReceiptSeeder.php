<?php

namespace Database\Seeders;

use App\Models\CustomerConfirmationMember;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReceiptSeeder extends Seeder
{
    public function run(): void
    {
        if (Receipt::query()->exists()) {
            $this->command->info('Receipts already seeded, skipping...');

            return;
        }

        $orders = Order::query()
            ->with([
                'invoices.receipt',
                'quotation.quotationItems',
            ])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->command->warn('No orders found; skipping receipt seeding.');

            return;
        }

        $forcedPartialInstallmentApplied = false;

        foreach ($orders as $index => $order) {
            if ($order->invoices->isEmpty()) {
                continue;
            }

            $memberIds = $order->quotation?->quotationItems
                ?->whereNotNull('customer_confirmation_member_id')
                ->pluck('customer_confirmation_member_id')
                ->unique()
                ->values()
                ->all() ?? [];

            if (empty($memberIds)) {
                continue;
            }

            $members = CustomerConfirmationMember::query()
                ->whereIn('id', $memberIds)
                ->orderBy('id')
                ->get();

            if ($members->isEmpty()) {
                continue;
            }

            $isInstallment = $order->invoices->count() > 1;

            if ($isInstallment && ! $forcedPartialInstallmentApplied) {
                $invoicesToPay = $order->invoices->take(1);
                $forcedPartialInstallmentApplied = true;
            } else {
                $paymentProfile = $order->id % 3;
                $invoicesToPay = $paymentProfile === 2
                    ? $order->invoices
                    : ($paymentProfile === 1 ? $order->invoices->take(1) : collect());
            }

            foreach ($invoicesToPay as $invoice) {
                if ($invoice->receipt->isNotEmpty()) {
                    continue;
                }

                $invoiceAmount = (float) $invoice->amount;

                $paymentMethod = $index % 2 === 0 ? 'transfer' : 'cash';

                $receipt = Receipt::query()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoiceAmount,
                    'receipt_date' => Carbon::now()->subDays(7 - $index)->toDateString(),
                    'payment_method' => $paymentMethod,
                    'reference' => $this->generateReference($paymentMethod),
                    'description' => 'Seeded receipt for per-member allocation',
                ]);

                $remaining = $invoiceAmount;
                $memberCount = max(1, $members->count());

                foreach ($members as $memberIndex => $member) {
                    $isLastMember = $memberIndex === $memberCount - 1;
                    $allocatedAmount = $isLastMember
                        ? $remaining
                        : round($invoiceAmount / $memberCount, 2);

                    ReceiptAllocation::query()->create([
                        'receipt_id' => $receipt->id,
                        'customer_confirmation_member_id' => $member->id,
                        'allocated_amount' => $allocatedAmount,
                        'notes' => $member->is_leader
                            ? 'Leader-paid allocation from seeded receipt'
                            : 'Member allocation from shared seeded receipt',
                    ]);

                    $remaining = round($remaining - $allocatedAmount, 2);
                }
            }
        }

        $this->command->info('Receipts and receipt allocations seeded successfully.');
    }

    private function generateReference(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'transfer' => 'TXN'.strtoupper(substr(uniqid('', true), -8)),
            'paynow' => 'PN'.now()->format('YmdHis').rand(100, 999),
            'cash' => 'CASH'.now()->format('Ymd').rand(100, 999),
            default => 'REF'.strtoupper(substr(uniqid('', true), -8)),
        };
    }
}
