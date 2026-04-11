<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EnquiryService
{
    public function __construct(
        public GeneralEnquiryService $generalEnquiryService,
        public PrivateEnquiryService $privateEnquiryService,
    ) {}

    /**
     * Get all enquiries from parent table for the datatable.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(array $filters = []): array
    {
        $query = Enquiry::query()
            ->with(['package', 'latestRemark', 'handledBy:id,name'])
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('contact_number', 'like', "%{$value}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v));

        $this->applySalesEnquiryScope($query);

        return $query
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($enquiry) {
                return [
                    'id' => $enquiry->id,
                    'type' => ucfirst($enquiry->type),
                    'status' => $enquiry->status->value,
                    'status_label' => $enquiry->status->label(),
                    'name' => $enquiry->name,
                    'contact' => $enquiry->contact_number,
                    'email' => $enquiry->email,
                    'child_id' => $this->getChildId($enquiry),
                    'package_id' => $enquiry->package_id,
                    'package_name' => $enquiry->package?->name ?? null,
                    'latest_remark' => $enquiry->latestRemark?->remark ?? '-',
                    'handled_by_name' => $enquiry->handledBy?->name ?? '-',
                    'created_at' => $enquiry->created_at?->translatedFormat('d F Y'),
                ];
            })
            ->all();
    }

    /**
     * Get the child enquiry ID for routing purposes.
     */
    private function getChildId(Enquiry $enquiry): ?int
    {
        if ($enquiry->type === 'general') {
            return $enquiry->generalEnquiry?->id;
        }

        return $enquiry->privateEnquiry?->id;
    }

    /**
     * Get summary counts for dashboard widgets.
     *
     * @return array{total: int, general: int, private: int, new_lead: int, contacted: int, negotiating: int, confirmed: int}
     */
    public function getSummaryCounts(): array
    {
        $query = Enquiry::query();
        $this->applySalesEnquiryScope($query);

        return [
            'total' => (clone $query)->count(),
            'general' => (clone $query)->where('type', 'general')->count(),
            'private' => (clone $query)->where('type', 'private')->count(),
            'new_lead' => (clone $query)->where('status', EnquiryStatus::NewLead)->count(),
            'contacted' => (clone $query)->where('status', EnquiryStatus::Contacted)->count(),
            'negotiating' => (clone $query)->where('status', 'negotiating')->count(),
            'confirmed' => (clone $query)->where('status', EnquiryStatus::Confirmed)->count(),
        ];
    }

    /**
     * Transition an enquiry's status.
     * Note: Transition to 'confirmed' is blocked here — use the confirm endpoint instead.
     */
    public function transitionStatus(int $id, string $newStatus): Enquiry
    {
        return DB::transaction(function () use ($id, $newStatus) {
            $enquiry = Enquiry::findOrFail($id);
            $targetStatus = EnquiryStatus::from($newStatus);

            // Block direct transition to confirmed — must go through confirm endpoint
            if ($targetStatus === EnquiryStatus::Confirmed) {
                abort(422, 'Cannot transition to confirmed directly. Use the confirm endpoint to submit a customer confirmation form.');
            }

            if (! $enquiry->status->canTransitionTo($targetStatus)) {
                abort(422, "Cannot transition from {$enquiry->status->label()} to {$targetStatus->label()}.");
            }

            $enquiry->update([
                'status' => $targetStatus->value,
                'handled_by' => auth()->id(),
            ]);

            activity()
                ->performedOn($enquiry)
                ->withProperties([
                    'subject_type' => 'Enquiry',
                    'subject_id' => $enquiry->id,
                    'old_status' => $enquiry->getOriginal('status'),
                    'new_status' => $targetStatus->value,
                ])
                ->log("Enquiry #{$enquiry->id} status changed to {$targetStatus->label()}");

            return $enquiry->fresh();
        });
    }

    /**
     * Confirm an enquiry atomically (transition to confirmed + create customer group).
     */
    public function confirmEnquiry(int $id): Enquiry
    {
        return DB::transaction(function () use ($id) {
            $enquiry = Enquiry::findOrFail($id);

            if ($enquiry->status === EnquiryStatus::Confirmed) {
                return $enquiry;
            }

            if (! $enquiry->status->canTransitionTo(EnquiryStatus::Confirmed)) {
                abort(422, "Cannot confirm enquiry from status {$enquiry->status->label()}. It must be in Contacted status.");
            }

            $enquiry->update([
                'status' => EnquiryStatus::Confirmed->value,
                'handled_by' => auth()->id(),
            ]);

            activity()
                ->performedOn($enquiry)
                ->withProperties([
                    'subject_type' => 'Enquiry',
                    'subject_id' => $enquiry->id,
                    'old_status' => $enquiry->getOriginal('status'),
                    'new_status' => EnquiryStatus::Confirmed->value,
                ])
                ->log("Enquiry #{$enquiry->id} confirmed");

            return $enquiry->fresh();
        });
    }

    /**
     * Get an enquiry by ID with its child relation.
     */
    public function getById(int $id): Enquiry
    {
        $query = Enquiry::with(['generalEnquiry', 'privateEnquiry', 'customerConfirmation.members.customer.user']);

        $this->applySalesEnquiryScope($query);

        return $query->findOrFail($id);
    }

    /**
     * Get enquiry status options for frontend.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getStatusOptions(): array
    {
        return EnquiryStatus::options();
    }

    /**
     * Update the package_id on an enquiry.
     */
    public function updatePackage(int $id, ?int $packageId): Enquiry
    {
        $enquiry = Enquiry::findOrFail($id);
        $enquiry->update(['package_id' => $packageId]);

        activity()
            ->performedOn($enquiry)
            ->withProperties([
                'subject_type' => 'Enquiry',
                'subject_id' => $enquiry->id,
                'package_id' => $packageId,
            ])
            ->log("Enquiry #{$enquiry->id} package updated");

        return $enquiry->fresh();
    }

    /**
     * Get confirmed enquiries that don't have a customer group yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConfirmedWithoutGroup(): array
    {
        $query = Enquiry::query()
            ->where('status', EnquiryStatus::Confirmed)
            ->whereDoesntHave('customerConfirmation');

        $this->applySalesEnquiryScope($query);

        return $query
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Enquiry $enquiry) => [
                'value' => $enquiry->id,
                'label' => "#{$enquiry->id} - {$enquiry->name} ({$enquiry->email})",
            ])
            ->all();
    }

    private function applySalesEnquiryScope(Builder $query): void
    {
        if (! DataScope::shouldScopeSalesEnquiries()) {
            return;
        }

        $query->where(function (Builder $visibilityQuery) {
            $visibilityQuery
                ->where('handled_by', auth()->id())
                ->orWhereNull('handled_by');
        });
    }
}
