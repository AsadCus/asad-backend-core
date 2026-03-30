<?php

namespace App\Services;

use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestRoom;
use App\Models\ManifestSharingGroup;
use App\Models\Quotation;

class PaymentStatusService
{
    public function __construct(private PackageSeatService $packageSeatService) {}

    /**
     * Minimum status required for auto-linking CC members to manifest.
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
        $outstandingAmount = $this->calculateInvoiceOutstandingAmount($invoice);

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
        $autoLinkStatuses = $this->getAutoLinkEligibleStatuses();

        foreach ($memberIds as $memberId) {
            $member = CustomerConfirmationMember::with('confirmation.package')->find((int) $memberId);

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

            $requiredAmount = $this->resolveMemberRequiredAmount($member);
            $paidAmount = $this->resolveMemberPaidAmount($memberInvoices, (int) $memberId);

            if ($requiredAmount !== null) {
                if ($paidAmount <= 0) {
                    $newStatus = 'pending_payment';
                } elseif ($paidAmount > $requiredAmount) {
                    $newStatus = 'overpaid';
                } elseif ($paidAmount >= $requiredAmount) {
                    $newStatus = 'fully_paid';
                } else {
                    $newStatus = 'partially_paid';
                }
            } else {
                $paidInvoicesCount = $memberInvoices->filter(function (Invoice $memberInvoice) {
                    return $this->isInvoiceSettled($memberInvoice);
                })->count();

                if ($paidInvoicesCount === $memberInvoices->count()) {
                    $newStatus = 'fully_paid';
                } elseif ($paidInvoicesCount > 0) {
                    $newStatus = 'partially_paid';
                } else {
                    $newStatus = 'pending_payment';
                }
            }

            $packageId = (int) ($member->confirmation?->package_id ?? 0);
            $isSeatConsumingStatus = in_array($newStatus, $autoLinkStatuses, true);

            if ($packageId > 0 && $isSeatConsumingStatus) {
                $hasAvailableSeat = $this->packageSeatService->hasAvailableSeat($packageId, (int) $member->id);

                if (! $hasAvailableSeat) {
                    CustomerConfirmationMember::query()
                        ->where('id', $memberId)
                        ->whereIn('status', ['pending_payment', 'partially_paid', 'fully_paid', 'overpaid'])
                        ->update(['status' => 'pending_payment']);

                    ManifestMember::query()
                        ->where('customer_confirmation_member_id', $memberId)
                        ->delete();

                    $this->packageSeatService->recalculateForPackageId($packageId);

                    continue;
                }
            }

            CustomerConfirmationMember::query()
                ->where('id', $memberId)
                ->whereIn('status', ['pending_payment', 'partially_paid', 'fully_paid', 'overpaid'])
                ->update(['status' => $newStatus]);

            if ($packageId > 0) {
                $this->packageSeatService->recalculateForPackageId($packageId);
            }
        }

        $this->autoLinkQuotationMembersToManifest($quotation, $memberIds, $autoLinkStatuses);

        $packageId = (int) ($quotation->customerConfirmation?->package_id ?? 0);
        if ($packageId > 0) {
            $this->packageSeatService->recalculateForPackageId($packageId);
        }
    }

    private function calculateInvoiceOutstandingAmount(Invoice $invoice): float
    {
        $invoiceAmount = (float) $invoice->amount;
        $totalPaid = (float) $invoice->receipt->sum('amount');

        if ($invoiceAmount >= 0) {
            return max(0, $invoiceAmount - $totalPaid);
        }

        return max(0, abs($invoiceAmount) - abs($totalPaid));
    }

    private function isInvoiceSettled(Invoice $invoice): bool
    {
        return $this->calculateInvoiceOutstandingAmount($invoice) <= 0;
    }

    /**
     * Return the list of statuses that qualify for auto-linking to a manifest.
     *
     * @return string[]
     */
    private function getAutoLinkEligibleStatuses(): array
    {
        $statuses = ['fully_paid', 'overpaid'];

        if (self::AUTO_LINK_MINIMUM_STATUS === 'partially_paid') {
            $statuses[] = 'partially_paid';
        }

        if (self::AUTO_LINK_MINIMUM_STATUS === 'pending_payment') {
            $statuses[] = 'partially_paid';
            $statuses[] = 'pending_payment';
        }

        return $statuses;
    }

    private function resolveMemberRequiredAmount(CustomerConfirmationMember $member): ?float
    {
        $package = $member->confirmation?->package;

        if (! $package) {
            return null;
        }

        $sharingPlan = strtolower(trim((string) ($member->sharing_plan ?? '')));

        return match ($sharingPlan) {
            'single' => (float) ($package->price_single ?? 0),
            'double' => (float) ($package->price_double ?? 0),
            'triple' => (float) ($package->price_triple ?? 0),
            'quad' => (float) ($package->price_quad ?? 0),
            'child_with_bed' => (float) ($package->child_with_bed_price ?? 0),
            'child_no_bed' => (float) ($package->child_no_bed_price ?? 0),
            'infant' => (float) ($package->infant_price ?? 0),
            default => 0.0,
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Invoice>  $memberInvoices
     */
    private function resolveMemberPaidAmount($memberInvoices, int $memberId): float
    {
        $paidAmount = 0.0;

        foreach ($memberInvoices as $invoice) {
            $invoiceItems = $invoice->quotationItems
                ->filter(fn ($item): bool => ! (bool) ($item->is_header ?? false))
                ->values();

            if ($invoiceItems->isEmpty()) {
                continue;
            }

            $invoiceSubtotal = (float) $invoiceItems->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

            if ($invoiceSubtotal <= 0) {
                continue;
            }

            $memberSubtotal = (float) $invoiceItems
                ->where('customer_confirmation_member_id', $memberId)
                ->sum(function ($item): float {
                    return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                });

            if ($memberSubtotal <= 0) {
                continue;
            }

            $receiptTotal = (float) $invoice->receipt->sum(function ($receipt): float {
                return (float) ($receipt->amount ?? 0);
            });

            if ($receiptTotal === 0.0) {
                continue;
            }

            $paidAmount += $receiptTotal * ($memberSubtotal / $invoiceSubtotal);
        }

        return round($paidAmount, 2);
    }

    /**
     * Auto-link paid members from one quotation into one sharing group and one room.
     */
    private function autoLinkQuotationMembersToManifest(
        Quotation $quotation,
        array $memberIds,
        array $autoLinkStatuses
    ): void {
        if (! $quotation->customer_confirmation_id) {
            return;
        }

        $confirmation = $quotation->customerConfirmation;

        if (! $confirmation || ! $confirmation->package_id) {
            return;
        }

        $manifest = Manifest::query()
            ->where('package_id', (int) $confirmation->package_id)
            ->first();

        if (! $manifest) {
            return;
        }

        $eligibleMembers = CustomerConfirmationMember::query()
            ->whereIn('id', array_map('intval', $memberIds))
            ->whereIn('status', $autoLinkStatuses)
            ->orderBy('id')
            ->get();

        if ($eligibleMembers->isEmpty()) {
            return;
        }

        $sharingGroup = ManifestSharingGroup::query()
            ->where('manifest_id', $manifest->id)
            ->where('source_quotation_id', $quotation->id)
            ->first();

        if (! $sharingGroup) {
            $sharingGroup = ManifestSharingGroup::query()->create([
                'manifest_id' => $manifest->id,
                'customer_confirmation_id' => (int) $quotation->customer_confirmation_id,
                'source_quotation_id' => (int) $quotation->id,
                'sort_order' => ((int) ManifestSharingGroup::query()->where('manifest_id', $manifest->id)->max('sort_order')) + 1,
                'remarks' => 'Auto-linked from quotation #'.$quotation->quotation_number,
            ]);
        }

        $memberIds = [];

        foreach ($eligibleMembers->values() as $index => $confirmationMember) {
            $manifestMember = ManifestMember::query()
                ->where('manifest_id', $manifest->id)
                ->where('customer_confirmation_member_id', (int) $confirmationMember->id)
                ->first();

            if (! $manifestMember) {
                $manifestMember = ManifestMember::query()->create([
                    'manifest_id' => $manifest->id,
                    'manifest_sharing_group_id' => $sharingGroup->id,
                    'customer_confirmation_member_id' => (int) $confirmationMember->id,
                    'sharing_plan' => $confirmationMember->sharing_plan,
                    'sort_order' => $index + 1,
                ]);
            } else {
                $manifestMember->update([
                    'manifest_sharing_group_id' => $sharingGroup->id,
                    'sharing_plan' => $confirmationMember->sharing_plan,
                ]);
            }

            $memberIds[] = (int) $manifestMember->id;
        }

        if (empty($memberIds)) {
            return;
        }

        $primarySharingPlan = (string) ($eligibleMembers->first()?->sharing_plan ?? 'single');

        $room = ManifestRoom::query()
            ->where('manifest_id', $manifest->id)
            ->where('source_quotation_id', $quotation->id)
            ->first();

        if (! $room) {
            $room = ManifestRoom::query()->create([
                'manifest_id' => $manifest->id,
                'source_quotation_id' => (int) $quotation->id,
                'sort_order' => ((int) ManifestRoom::query()->where('manifest_id', $manifest->id)->max('sort_order')) + 1,
                'room_type' => $primarySharingPlan,
                'sharing_plan' => $primarySharingPlan,
                'capacity' => $this->capacityFromSharingPlan($primarySharingPlan),
                'status' => 'pending',
                'remarks' => 'Auto-linked from quotation #'.$quotation->quotation_number,
            ]);
        } else {
            $room->update([
                'room_type' => $primarySharingPlan,
                'sharing_plan' => $primarySharingPlan,
                'capacity' => $this->capacityFromSharingPlan($primarySharingPlan),
            ]);
        }

        $room->roomMembers()
            ->whereNotIn('manifest_member_id', $memberIds)
            ->delete();

        foreach (array_values($memberIds) as $index => $memberId) {
            $room->roomMembers()->updateOrCreate(
                ['manifest_member_id' => $memberId],
                ['sort_order' => $index + 1]
            );
        }
    }

    private function capacityFromSharingPlan(?string $sharingPlan): int
    {
        return match (strtolower((string) $sharingPlan)) {
            'quad' => 4,
            'triple' => 3,
            'double' => 2,
            default => 1,
        };
    }
}
