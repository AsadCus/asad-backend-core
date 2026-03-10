<?php

namespace App\Services;

use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestTraveler;

class PaymentStatusService
{
    public function __construct(private PackageSeatService $packageSeatService) {}

    /**
     * Minimum status required for auto-linking CC members to manifest.
     *
     * Mode B (default): only members who reach 'partially_paid' or above.
     * To link on 'pending_payment' instead, change this to 'pending_payment'.
     */
    private const AUTO_LINK_MINIMUM_STATUS = 'partially_paid';

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
            $member = CustomerConfirmationMember::with('confirmation')->find((int) $memberId);

            if (! $member) {
                continue;
            }

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

            $packageId = (int) ($member->confirmation?->package_id ?? 0);
            $isSeatConsumingStatus = in_array($newStatus, $this->getAutoLinkEligibleStatuses(), true);

            if ($packageId > 0 && $isSeatConsumingStatus) {
                $hasAvailableSeat = $this->packageSeatService->hasAvailableSeat($packageId, (int) $member->id);

                if (! $hasAvailableSeat) {
                    CustomerConfirmationMember::query()
                        ->where('id', $memberId)
                        ->whereIn('status', ['pending_payment', 'partially_paid', 'confirmed', 'unavailable'])
                        ->update(['status' => 'unavailable']);

                    ManifestTraveler::query()
                        ->where('customer_confirmation_member_id', $memberId)
                        ->update(['status' => 'cancelled']);

                    $this->packageSeatService->recalculateForPackageId($packageId);

                    continue;
                }
            }

            CustomerConfirmationMember::query()
                ->where('id', $memberId)
                ->whereIn('status', ['pending_payment', 'partially_paid', 'confirmed', 'unavailable'])
                ->update(['status' => $newStatus]);

            // Auto-link member to manifest when they reach the minimum status
            if ($isSeatConsumingStatus) {
                $this->autoLinkMemberToManifest($memberId);
            }

            if ($packageId > 0) {
                $this->packageSeatService->recalculateForPackageId($packageId);
            }
        }
    }

    /**
     * Return the list of statuses that qualify for auto-linking to a manifest.
     *
     * @return string[]
     */
    private function getAutoLinkEligibleStatuses(): array
    {
        $statuses = ['confirmed'];

        if (self::AUTO_LINK_MINIMUM_STATUS === 'partially_paid') {
            $statuses[] = 'partially_paid';
        }

        if (self::AUTO_LINK_MINIMUM_STATUS === 'pending_payment') {
            $statuses[] = 'partially_paid';
            $statuses[] = 'pending_payment';
        }

        return $statuses;
    }

    /**
     * Auto-link a CC member to their package's manifest as a traveler.
     *
     * If the member already exists as a traveler in the manifest, skip.
     */
    private function autoLinkMemberToManifest(int $memberId): void
    {
        $member = CustomerConfirmationMember::with(['confirmation', 'customer'])->find($memberId);

        if (! $member || ! $member->confirmation?->package_id) {
            return;
        }

        $manifest = Manifest::where('package_id', $member->confirmation->package_id)->first();

        if (! $manifest) {
            return;
        }

        // Skip if already linked
        $alreadyLinked = $manifest->travelers()
            ->where('customer_confirmation_member_id', $memberId)
            ->exists();

        if ($alreadyLinked) {
            return;
        }

        $nextSn = ($manifest->travelers()->max('sn') ?? 0) + 1;

        $manifest->travelers()->create([
            'sn' => $nextSn,
            'customer_id' => $member->customer_id,
            'customer_confirmation_member_id' => $memberId,
            'name_as_per_passport' => $member->customer?->name ?? '',
            'status' => 'assigned',
        ]);
    }
}
