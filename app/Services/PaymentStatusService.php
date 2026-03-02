<?php

namespace App\Services;

use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;

class PaymentStatusService
{
    public function syncAfterReceiptMutation(int $invoiceId): void
    {
        $invoice = Invoice::with([
            'receipt',
            'order.invoices.receipt',
            'order.invoices.quotationItems',
            'order.quotation.quotationItems',
        ])->find($invoiceId);

        if (! $invoice) {
            return;
        }

        $this->syncInvoiceStatus($invoice);
        $this->syncConfirmationMemberStatus($invoice);
    }

    public function syncAfterReceiptReassignment(?int $oldInvoiceId, int $newInvoiceId): void
    {
        if ($oldInvoiceId !== null && $oldInvoiceId !== $newInvoiceId) {
            $this->syncAfterReceiptMutation($oldInvoiceId);
        }

        $this->syncAfterReceiptMutation($newInvoiceId);
    }

    private function syncInvoiceStatus(Invoice $invoice): void
    {
        $totalPaid = (float) $invoice->receipt->sum('amount');
        $outstandingAmount = max(0, (float) $invoice->amount - $totalPaid);

        if ($outstandingAmount <= 0) {
            $newStatus = 'paid';
        } else {
            $newStatus = 'issued';
        }

        if ($invoice->status !== $newStatus) {
            $invoice->update(['status' => $newStatus]);
        }
    }

    private function syncConfirmationMemberStatus(Invoice $invoice): void
    {
        if (! $invoice->order || ! $invoice->order->quotation) {
            return;
        }

        $quotation = $invoice->order->quotation;

        if (! $quotation->customer_confirmation_id) {
            return;
        }

        $memberIds = $quotation->quotationItems
            ->whereNotNull('customer_confirmation_member_id')
            ->pluck('customer_confirmation_member_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($memberIds)) {
            return;
        }

        $orderInvoices = $invoice->order->invoices;

        foreach ($memberIds as $memberId) {
            $memberInvoices = $orderInvoices
                ->filter(function (Invoice $orderInvoice) use ($memberId) {
                    return $orderInvoice->quotationItems
                        ->contains(fn ($item) => (int) $item->customer_confirmation_member_id === (int) $memberId);
                })
                ->values();

            if ($memberInvoices->isEmpty()) {
                continue;
            }

            $paidInvoicesCount = $memberInvoices->filter(function (Invoice $memberInvoice) {
                $invoicePaid = (float) $memberInvoice->receipt->sum('amount');

                return $invoicePaid >= (float) $memberInvoice->amount;
            })->count();

            if ($paidInvoicesCount === $memberInvoices->count()) {
                $newStatus = 'confirmed';
            } elseif ($paidInvoicesCount > 0) {
                $newStatus = 'partially_paid';
            } else {
                $newStatus = 'pending_payment';
            }

            CustomerConfirmationMember::query()
                ->where('id', $memberId)
                ->whereIn('status', ['pending_payment', 'partially_paid', 'confirmed'])
                ->update(['status' => $newStatus]);
        }
    }
}
