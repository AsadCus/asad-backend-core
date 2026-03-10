<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\QuotationItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

            if ($quotationItems->isEmpty()) {
                continue;
            }

            $invoiceDate = Carbon::parse($order->quotation?->quotation_date ?? now())
                ->addDays(3 + min($index, 8));

            $paymentPlan = (string) ($order->payment_plan ?: $order->quotation?->payment_plan ?: 'full');

            if ($paymentPlan === 'installment') {
                $splitItems = $this->buildInstallmentItems(
                    $quotationItems,
                    $order->quotation?->id,
                    'fixed',
                    500
                );

                $installmentInvoices = [
                    [
                        'type' => 'deposit',
                        'description' => 'Invoice For Deposit',
                        'item_ids' => $splitItems['deposit_ids'],
                        'amount' => $splitItems['deposit_amount'],
                        'invoice_date' => $invoiceDate->toDateString(),
                        'due_date' => $invoiceDate->copy()->addDays(7)->toDateString(),
                    ],
                    [
                        'type' => 'installment',
                        'description' => 'Invoice For 50%',
                        'item_ids' => $splitItems['fifty_ids'],
                        'amount' => $splitItems['fifty_amount'],
                        'invoice_date' => $invoiceDate->copy()->addDays(10)->toDateString(),
                        'due_date' => $invoiceDate->copy()->addDays(21)->toDateString(),
                    ],
                    [
                        'type' => 'handover',
                        'description' => 'Invoice For Balance',
                        'item_ids' => $splitItems['balance_ids'],
                        'amount' => $splitItems['balance_amount'],
                        'invoice_date' => $invoiceDate->copy()->addDays(24)->toDateString(),
                        'due_date' => $invoiceDate->copy()->addDays(35)->toDateString(),
                    ],
                ];

                foreach ($installmentInvoices as $seededInvoice) {
                    $invoice = Invoice::query()->create([
                        'order_id' => $order->id,
                        'type' => $seededInvoice['type'],
                        'description' => $seededInvoice['description'],
                        'amount' => $seededInvoice['amount'],
                        'invoice_date' => $seededInvoice['invoice_date'],
                        'due_date' => $seededInvoice['due_date'],
                        'status' => 'issued',
                    ]);

                    if (! empty($seededInvoice['item_ids'])) {
                        $invoice->quotationItems()->sync($seededInvoice['item_ids']);
                    }
                }

                continue;
            }

            $itemIds = $quotationItems
                ->where('is_header', false)
                ->pluck('id')
                ->filter()
                ->values()
                ->all();

            $amount = round((float) $quotationItems->sum(function (QuotationItem $item): float {
                if ($item->is_header) {
                    return 0;
                }

                $quantity = (float) ($item->quantity ?? 0);
                $rate = (float) ($item->rate ?? 0);

                return $quantity * $rate;
            }), 2);

            if ($amount <= 0) {
                continue;
            }

            $invoice = Invoice::query()->create([
                'order_id' => $order->id,
                'type' => 'deposit',
                'description' => $paymentPlan === 'direct' ? null : 'Invoice For Full Payment',
                'amount' => $amount,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays(14)->toDateString(),
                'status' => 'issued',
            ]);

            if (! empty($itemIds)) {
                $invoice->quotationItems()->sync($itemIds);
            }
        }

        $this->command->info('Invoices seeded successfully.');
    }

    /**
     * @return array{deposit_ids: array<int>, fifty_ids: array<int>, balance_ids: array<int>, deposit_amount: float, fifty_amount: float, balance_amount: float}
     */
    private function buildInstallmentItems(Collection $quotationItems, ?int $quotationId, string $depositType = 'fixed', float $depositValue = 500): array
    {
        $normalizedSourceItems = $this->mergeSplitInstallmentItems($quotationItems);

        $packageItems = $normalizedSourceItems->filter(function (QuotationItem $item): bool {
            return ! $item->is_header && (int) ($item->customer_confirmation_member_id ?? 0) > 0;
        })->values();

        $nonPackageItemIds = $normalizedSourceItems
            ->filter(function (QuotationItem $item): bool {
                return $item->is_header || (int) ($item->customer_confirmation_member_id ?? 0) <= 0;
            })
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $depositIds = $nonPackageItemIds;
        $fiftyIds = [];
        $balanceIds = [];

        $depositAmount = round((float) $normalizedSourceItems
            ->filter(fn (QuotationItem $item): bool => $item->is_header || (int) ($item->customer_confirmation_member_id ?? 0) <= 0)
            ->sum(fn (QuotationItem $item): float => $this->lineAmount($item)), 2);
        $fiftyAmount = 0.0;
        $balanceAmount = 0.0;

        foreach ($packageItems as $item) {
            $quantity = max(1.0, (float) ($item->quantity ?? 1));
            $amount = $this->lineAmount($item);

            $perItemDepositAmount = match (true) {
                $depositType === 'percentage' && $depositValue > 0 => round($amount * ($depositValue / 100), 2),
                $depositType === 'fixed' && $depositValue > 0 => round(min($depositValue, $amount), 2),
                default => 0.0,
            };

            $depositLineAmount = min($perItemDepositAmount, $amount);
            $fiftyTarget = round($amount * 0.5, 2);
            $remainingAfterDeposit = round($amount - $depositLineAmount, 2);
            $fiftyLineAmount = min($fiftyTarget, $remainingAfterDeposit);
            $balanceLineAmount = round($amount - $depositLineAmount - $fiftyLineAmount, 2);

            if ($depositLineAmount > 0) {
                $depositItem = $this->createSplitQuotationItem(
                    $item,
                    $quotationId,
                    'Deposit',
                    $quantity,
                    $depositLineAmount
                );

                if ($depositItem) {
                    $depositIds[] = $depositItem->id;
                    $depositAmount = round($depositAmount + $depositLineAmount, 2);
                }
            }

            if ($fiftyLineAmount > 0) {
                $fiftyItem = $this->createSplitQuotationItem(
                    $item,
                    $quotationId,
                    '50%',
                    $quantity,
                    $fiftyLineAmount
                );

                if ($fiftyItem) {
                    $fiftyIds[] = $fiftyItem->id;
                    $fiftyAmount = round($fiftyAmount + $fiftyLineAmount, 2);
                }
            }

            if ($balanceLineAmount > 0) {
                $balanceItem = $this->createSplitQuotationItem(
                    $item,
                    $quotationId,
                    'Balance',
                    $quantity,
                    $balanceLineAmount
                );

                if ($balanceItem) {
                    $balanceIds[] = $balanceItem->id;
                    $balanceAmount = round($balanceAmount + $balanceLineAmount, 2);
                }
            }
        }

        return [
            'deposit_ids' => $depositIds,
            'fifty_ids' => $fiftyIds,
            'balance_ids' => $balanceIds,
            'deposit_amount' => $depositAmount,
            'fifty_amount' => $fiftyAmount,
            'balance_amount' => $balanceAmount,
        ];
    }

    private function createSplitQuotationItem(QuotationItem $item, ?int $quotationId, string $suffix, float $quantity, float $amount): ?QuotationItem
    {
        if ($amount <= 0) {
            return null;
        }

        $effectiveQuantity = max(1.0, $quantity);
        $rate = round($amount / $effectiveQuantity, 2);

        return QuotationItem::query()->create([
            'quotation_id' => $quotationId ?? $item->quotation_id,
            'customer_confirmation_member_id' => $item->customer_confirmation_member_id,
            'parent_id' => $item->parent_id,
            'description' => sprintf('%s (%s)', $this->stripInstallmentSuffix($item->description), $suffix),
            'is_header' => false,
            'quantity' => $effectiveQuantity,
            'rate' => $rate,
            'sort_order' => $item->sort_order,
        ]);
    }

    private function mergeSplitInstallmentItems(Collection $items): Collection
    {
        $grouped = [];
        $untouchedItems = collect();

        foreach ($items as $item) {
            if (! $item instanceof QuotationItem) {
                continue;
            }

            $memberId = (int) ($item->customer_confirmation_member_id ?? 0);
            $originalDescription = trim((string) ($item->description ?? ''));
            $baseDescription = $this->stripInstallmentSuffix($item->description);
            $hasInstallmentSuffix = $originalDescription !== '' && $originalDescription !== $baseDescription;

            if ($item->is_header || $memberId <= 0 || $baseDescription === '' || ! $hasInstallmentSuffix) {
                $untouchedItems->push($item);

                continue;
            }

            $groupKey = implode('|', [
                $memberId,
                $item->parent_id ?? '',
                $baseDescription,
            ]);

            if (! array_key_exists($groupKey, $grouped)) {
                $grouped[$groupKey] = [
                    'base_item' => $item,
                    'total_amount' => $this->lineAmount($item),
                ];

                continue;
            }

            $grouped[$groupKey]['total_amount'] = round(
                $grouped[$groupKey]['total_amount'] + $this->lineAmount($item),
                2
            );
        }

        $mergedItems = collect();

        foreach ($grouped as $group) {
            /** @var QuotationItem $baseItem */
            $baseItem = $group['base_item'];
            $amount = (float) $group['total_amount'];
            $quantity = max(1.0, (float) ($baseItem->quantity ?? 1));
            $rate = round($amount / $quantity, 2);

            $merged = clone $baseItem;
            $merged->description = $this->stripInstallmentSuffix($baseItem->description);
            $merged->quantity = $quantity;
            $merged->rate = $rate;

            $mergedItems->push($merged);
        }

        return $untouchedItems
            ->concat($mergedItems)
            ->sortBy(function (QuotationItem $item): int {
                return (int) ($item->sort_order ?? 0);
            })
            ->values();
    }

    private function stripInstallmentSuffix(?string $description): string
    {
        if (! $description) {
            return '';
        }

        return trim((string) preg_replace('/\s*\((Deposit|50%|Balance)\)$/i', '', $description));
    }

    private function lineAmount(QuotationItem $item): float
    {
        $quantity = (float) ($item->quantity ?? 0);
        $rate = (float) ($item->rate ?? 0);

        return round($quantity * $rate, 2);
    }
}
