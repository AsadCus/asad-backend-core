<?php

namespace App\Services;

use App\Enums\PackageProposalStatus;
use App\Helpers\FormatService;
use App\Models\Country;
use App\Models\Package;
use App\Models\PackageProposal;
use App\Models\User;
use App\Services\UserRoles\OfficialUserService;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PackageProposalService
{
    public function __construct(
        private FormatService $formatService,
        private NumberingService $numberingService,
        private NotificationService $notificationService,
        private PackageService $packageService,
        private OfficialUserService $officialUserService,
    ) {}

    public function getForDataTable(array $filters = [])
    {
        $query = PackageProposal::query()
            ->with(['country', 'createdBy']);

        $this->applyCountryScope($query);

        return $query
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('name', 'like', "%{$value}%")
                        ->orWhere('proposal_number', 'like', "%{$value}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $value) {
                $q->where('status', $value);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($proposal) {
                return [
                    'id' => $proposal->id,
                    'proposal_number' => $proposal->proposal_number,
                    'name' => $proposal->name,
                    'status' => $proposal->status->value,
                    'status_label' => $proposal->status->label(),
                    'country_name' => $proposal->country?->name,
                    'currency_symbol' => $proposal->currency_symbol,
                    'departure_date' => $proposal->departure_date_formatted,
                    'return_date' => $proposal->return_date_formatted,
                    'total_seats' => $proposal->total_seats,
                    'created_by_name' => $proposal->createdBy?->name,
                    'created_at' => $proposal->created_at?->translatedFormat('d F Y'),
                    'package_id' => $proposal->package_id,
                ];
            });
    }

    public function store(array $data): PackageProposal
    {
        return DB::transaction(function () use ($data) {
            $proposalNumber = $this->numberingService->ensureNumber(
                'package_proposal',
                $data['proposal_number'] ?? null,
            );

            $country = isset($data['country_id']) ? Country::find($data['country_id']) : null;

            $proposal = PackageProposal::create([
                'proposal_number' => $proposalNumber,
                'name' => $data['name'],
                'status' => PackageProposalStatus::Draft->value,
                'country_id' => $data['country_id'] ?? null,
                'currency_symbol' => $data['currency_symbol'] ?? $country?->currency_symbol,
                'departure_date' => $data['departure_date'] ?? null,
                'return_date' => $data['return_date'] ?? null,
                'total_seats' => $data['total_seats'] ?? 0,
                'price_single' => $data['price_single'] ?? 0,
                'price_double' => $data['price_double'] ?? 0,
                'price_triple' => $data['price_triple'] ?? 0,
                'price_quad' => $data['price_quad'] ?? 0,
                'child_with_bed_price' => $data['child_with_bed_price'] ?? 0,
                'child_no_bed_price' => $data['child_no_bed_price'] ?? 0,
                'infant_price' => $data['infant_price'] ?? 0,
                'expenditure' => $this->normalizeExpenditure($data['expenditure'] ?? []),
                'passenger_simulation' => $data['passenger_simulation'] ?? null,
                'officials' => $this->normalizeOfficials($data['officials'] ?? []),
                'created_by' => auth()->id(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            activity()
                ->performedOn($proposal)
                ->withProperties(['subject_type' => 'PackageProposal', 'subject_id' => $proposal->id])
                ->log('Package proposal created #'.$proposal->proposal_number);

            return $proposal;
        });
    }

    public function getForEditShow(int $id): array
    {
        $query = PackageProposal::with(['country', 'createdBy', 'submittedBy', 'approvedRejectedBy', 'package']);

        $this->applyCountryScope($query);

        $proposal = $query->findOrFail($id);

        return [
            'id' => $proposal->id,
            'proposal_number' => $proposal->proposal_number,
            'name' => $proposal->name,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
            'country_id' => $proposal->country_id,
            'country_name' => $proposal->country?->name,
            'currency_symbol' => $proposal->currency_symbol,
            'departure_date' => $proposal->departure_date_formatted,
            'return_date' => $proposal->return_date_formatted,
            'total_seats' => $proposal->total_seats,
            'price_single' => $this->formatService->cleanDecimal($proposal->price_single),
            'price_double' => $this->formatService->cleanDecimal($proposal->price_double),
            'price_triple' => $this->formatService->cleanDecimal($proposal->price_triple),
            'price_quad' => $this->formatService->cleanDecimal($proposal->price_quad),
            'child_with_bed_price' => $this->formatService->cleanDecimal($proposal->child_with_bed_price),
            'child_no_bed_price' => $this->formatService->cleanDecimal($proposal->child_no_bed_price),
            'infant_price' => $this->formatService->cleanDecimal($proposal->infant_price),
            'expenditure' => $proposal->expenditure ?? [],
            'passenger_simulation' => $proposal->passenger_simulation ?? [
                'single' => 0,
                'double' => 0,
                'triple' => 0,
                'quad' => 0,
                'child_with_bed' => 0,
                'child_no_bed' => 0,
                'infant' => 0,
            ],
            'officials' => $proposal->officials ?? [],
            'approver_user_ids' => $proposal->approver_user_ids ?? [],
            'submitted_at' => $proposal->submitted_at?->translatedFormat('d F Y H:i'),
            'submitted_by_name' => $proposal->submittedBy?->name,
            'approved_rejected_at' => $proposal->approved_rejected_at?->translatedFormat('d F Y H:i'),
            'approved_rejected_by_name' => $proposal->approvedRejectedBy?->name,
            'rejection_reason' => $proposal->rejection_reason,
            'created_by' => $proposal->created_by,
            'created_by_name' => $proposal->createdBy?->name,
            'package_id' => $proposal->package_id,
            'package_number' => $proposal->package?->package_number,
            'remarks' => $proposal->remarks,
            'created_at' => $proposal->created_at?->translatedFormat('d F Y'),
        ];
    }

    public function update(array $data, int $id): PackageProposal
    {
        return DB::transaction(function () use ($data, $id) {
            $proposal = PackageProposal::findOrFail($id);

            if (! in_array($proposal->status, [PackageProposalStatus::Draft, PackageProposalStatus::Rejected])) {
                abort(422, 'Proposal can only be edited in draft or rejected status.');
            }

            $country = isset($data['country_id']) ? Country::find($data['country_id']) : null;

            $proposal->update([
                'name' => $data['name'],
                'country_id' => $data['country_id'] ?? $proposal->country_id,
                'currency_symbol' => $data['currency_symbol'] ?? $country?->currency_symbol ?? $proposal->currency_symbol,
                'departure_date' => $data['departure_date'] ?? null,
                'return_date' => $data['return_date'] ?? null,
                'total_seats' => $data['total_seats'] ?? $proposal->total_seats,
                'price_single' => $data['price_single'] ?? $proposal->price_single,
                'price_double' => $data['price_double'] ?? $proposal->price_double,
                'price_triple' => $data['price_triple'] ?? $proposal->price_triple,
                'price_quad' => $data['price_quad'] ?? $proposal->price_quad,
                'child_with_bed_price' => $data['child_with_bed_price'] ?? $proposal->child_with_bed_price,
                'child_no_bed_price' => $data['child_no_bed_price'] ?? $proposal->child_no_bed_price,
                'infant_price' => $data['infant_price'] ?? $proposal->infant_price,
                'expenditure' => $this->normalizeExpenditure($data['expenditure'] ?? []),
                'passenger_simulation' => $data['passenger_simulation'] ?? $proposal->passenger_simulation,
                'officials' => $this->normalizeOfficials($data['officials'] ?? []),
                'remarks' => $data['remarks'] ?? null,
            ]);

            if ($proposal->status === PackageProposalStatus::Rejected) {
                $proposal->update([
                    'status' => PackageProposalStatus::Draft->value,
                    'rejection_reason' => null,
                    'approved_rejected_at' => null,
                    'approved_rejected_by' => null,
                ]);
            }

            activity()
                ->performedOn($proposal)
                ->withProperties(['subject_type' => 'PackageProposal', 'subject_id' => $proposal->id])
                ->log('Package proposal updated #'.$proposal->proposal_number);

            return $proposal->fresh();
        });
    }

    public function delete(int $id): bool
    {
        $proposal = PackageProposal::findOrFail($id);

        if ($proposal->status !== PackageProposalStatus::Draft) {
            abort(422, 'Only draft proposals can be deleted.');
        }

        $proposal->delete();

        return true;
    }

    public function submitForApproval(int $id, array $approverUserIds): PackageProposal
    {
        return DB::transaction(function () use ($id, $approverUserIds) {
            $proposal = PackageProposal::findOrFail($id);

            if ($proposal->status !== PackageProposalStatus::Draft) {
                abort(422, 'Only draft proposals can be submitted for approval.');
            }

            $proposal->update([
                'status' => PackageProposalStatus::PendingApproval->value,
                'approver_user_ids' => $approverUserIds,
                'submitted_at' => now(),
                'submitted_by' => auth()->id(),
            ]);

            $submitterName = auth()->user()->name ?? 'A user';

            $this->notificationService->createNotification(
                [
                    'title' => 'Package PnL Approval Required',
                    'message' => "{$submitterName} submitted proposal #{$proposal->proposal_number} - {$proposal->name} for your approval.",
                    'type' => 'info',
                    'link' => "/package-proposals/{$proposal->id}",
                    'exclusive' => true,
                ],
                userIds: $approverUserIds,
            );

            activity()
                ->performedOn($proposal)
                ->withProperties(['subject_type' => 'PackageProposal', 'subject_id' => $proposal->id])
                ->log('Package proposal submitted for approval #'.$proposal->proposal_number);

            return $proposal->fresh();
        });
    }

    public function approve(int $id): PackageProposal
    {
        return DB::transaction(function () use ($id) {
            $proposal = PackageProposal::findOrFail($id);

            if ($proposal->status !== PackageProposalStatus::PendingApproval) {
                abort(422, 'Only pending proposals can be approved.');
            }

            $approverIds = $proposal->approver_user_ids ?? [];
            if (! in_array(auth()->id(), $approverIds)) {
                abort(403, 'You are not authorized to approve this proposal.');
            }

            $proposal->update([
                'status' => PackageProposalStatus::Approved->value,
                'approved_rejected_at' => now(),
                'approved_rejected_by' => auth()->id(),
            ]);

            $this->notificationService->createNotification(
                [
                    'title' => 'Package PnL Approved',
                    'message' => "Your proposal #{$proposal->proposal_number} - {$proposal->name} has been approved.",
                    'type' => 'success',
                    'link' => "/package-proposals/{$proposal->id}",
                ],
                userIds: [$proposal->created_by],
            );

            activity()
                ->performedOn($proposal)
                ->withProperties(['subject_type' => 'PackageProposal', 'subject_id' => $proposal->id])
                ->log('Package proposal approved #'.$proposal->proposal_number);

            return $proposal->fresh();
        });
    }

    public function reject(int $id, string $reason): PackageProposal
    {
        return DB::transaction(function () use ($id, $reason) {
            $proposal = PackageProposal::findOrFail($id);

            if ($proposal->status !== PackageProposalStatus::PendingApproval) {
                abort(422, 'Only pending proposals can be rejected.');
            }

            $approverIds = $proposal->approver_user_ids ?? [];
            if (! in_array(auth()->id(), $approverIds)) {
                abort(403, 'You are not authorized to reject this proposal.');
            }

            $proposal->update([
                'status' => PackageProposalStatus::Rejected->value,
                'approved_rejected_at' => now(),
                'approved_rejected_by' => auth()->id(),
                'rejection_reason' => $reason,
            ]);

            $this->notificationService->createNotification(
                [
                    'title' => 'Package PnL Rejected',
                    'message' => "Your proposal #{$proposal->proposal_number} - {$proposal->name} has been rejected. Reason: {$reason}",
                    'type' => 'error',
                    'link' => "/package-proposals/{$proposal->id}",
                ],
                userIds: [$proposal->created_by],
            );

            activity()
                ->performedOn($proposal)
                ->withProperties(['subject_type' => 'PackageProposal', 'subject_id' => $proposal->id])
                ->log('Package proposal rejected #'.$proposal->proposal_number);

            return $proposal->fresh();
        });
    }

    public function createPackageFromProposal(int $id): Package
    {
        return DB::transaction(function () use ($id) {
            $proposal = PackageProposal::findOrFail($id);

            if ($proposal->status !== PackageProposalStatus::Approved) {
                abort(422, 'Only approved proposals can create packages.');
            }

            if ($proposal->package_id !== null) {
                abort(422, 'A package has already been created from this proposal.');
            }

            $officials = collect($proposal->officials ?? [])->map(fn ($o) => [
                'official_id' => $o['official_id'] ?? null,
                'type' => $o['type'] ?? null,
                'name' => $o['name'] ?? 'Official',
                'contact_number' => $o['contact_number'] ?? null,
                'nationality' => $o['nationality'] ?? null,
                'passport_number' => $o['passport_number'] ?? null,
                'gender' => $o['gender'] ?? null,
                'date_of_birth' => $o['date_of_birth'] ?? null,
                'passport_issue_date' => $o['passport_issue_date'] ?? null,
                'passport_expiry_date' => $o['passport_expiry_date'] ?? null,
                'passport_place_of_issue' => $o['passport_place_of_issue'] ?? null,
                'place_of_birth' => $o['place_of_birth'] ?? null,
            ])->toArray();

            $package = $this->packageService->store([
                'name' => $proposal->name,
                'status' => 'open',
                'country_id' => $proposal->country_id,
                'total_seats' => $proposal->total_seats,
                'departure_date' => $proposal->departure_date?->format('d F Y'),
                'return_date' => $proposal->return_date?->format('d F Y'),
                'price_single' => $proposal->price_single,
                'price_double' => $proposal->price_double,
                'price_triple' => $proposal->price_triple,
                'price_quad' => $proposal->price_quad,
                'child_with_bed_price' => $proposal->child_with_bed_price,
                'child_no_bed_price' => $proposal->child_no_bed_price,
                'infant_price' => $proposal->infant_price,
                'accommodations' => [
                    ['location' => 'TBD', 'hotel_name' => 'TBD'],
                ],
                'officials' => $officials,
            ]);

            $proposal->update(['package_id' => $package->id]);

            activity()
                ->performedOn($proposal)
                ->withProperties([
                    'subject_type' => 'PackageProposal',
                    'subject_id' => $proposal->id,
                    'package_id' => $package->id,
                ])
                ->log('Package created from proposal #'.$proposal->proposal_number);

            return $package;
        });
    }

    /**
     * Get superadmin users filtered by proposal's country.
     *
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    public function getApproverOptions(?int $countryId = null)
    {
        $query = User::role('superadmin');

        if ($countryId) {
            $query->where(function ($q) use ($countryId) {
                $q->whereDoesntHave('admin')
                    ->orWhereHas('admin', function ($adminQuery) use ($countryId) {
                        $adminQuery->where(function ($inner) use ($countryId) {
                            $inner->where('country_id', $countryId)
                                ->orWhereJsonContains('country_ids', $countryId)
                                ->orWhereJsonContains('country_ids', (string) $countryId);
                        });
                    });
            });
        }

        return $query->orderBy('name')->get()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    private function applyCountryScope(Builder $query): void
    {
        $user = DataScope::user();

        if (! $user || ! DataScope::shouldScopePackageAndManifestCountry($user)) {
            return;
        }

        $countryIds = DataScope::scopedCountryIds($user);

        if (empty($countryIds)) {
            return;
        }

        $query->whereIn('country_id', $countryIds);
    }

    private function normalizeExpenditure(mixed $expenditure): array
    {
        if (! is_array($expenditure)) {
            return [];
        }

        $sections = array_is_list($expenditure) ? $expenditure : [];
        $normalized = [];

        foreach ($sections as $sectionIndex => $section) {
            if (! is_array($section)) {
                continue;
            }

            $itemsInput = isset($section['items']) && is_array($section['items'])
                ? (array_is_list($section['items']) ? $section['items'] : [])
                : [];
            $items = [];

            foreach ($itemsInput as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $items[] = [
                    'item_name' => $this->normalizeNullableString($item['item_name'] ?? null),
                    'unit_price' => is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0.0,
                    'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 0.0,
                    'remarks' => $this->normalizeNullableString($item['remarks'] ?? null),
                    'sort_order' => isset($item['sort_order']) && is_numeric($item['sort_order'])
                        ? (int) $item['sort_order']
                        : ($itemIndex + 1),
                ];
            }

            $extensions = $this->normalizeExpenditureExtensions($section['extensions'] ?? []);

            $normalized[] = [
                'title' => $this->normalizeNullableString($section['title'] ?? null),
                'sort_order' => isset($section['sort_order']) && is_numeric($section['sort_order'])
                    ? (int) $section['sort_order']
                    : ($sectionIndex + 1),
                'items' => $items,
                'extensions' => $extensions,
            ];
        }

        return $normalized;
    }

    private function normalizeExpenditureExtensions(mixed $extensions): array
    {
        if (! is_array($extensions)) {
            return [];
        }

        $extensionList = array_is_list($extensions) ? $extensions : [];
        $normalized = [];

        foreach ($extensionList as $extensionIndex => $extension) {
            if (! is_array($extension)) {
                continue;
            }

            $mode = $this->normalizeNullableString($extension['calculation_mode'] ?? null);
            $normalized[] = [
                'name' => $this->normalizeNullableString($extension['name'] ?? null),
                'calculation_mode' => in_array($mode, ['fixed', 'percentage'], true) ? $mode : 'fixed',
                'calculation_value' => is_numeric($extension['calculation_value'] ?? null)
                    ? (float) $extension['calculation_value']
                    : 0.0,
                'sort_order' => isset($extension['sort_order']) && is_numeric($extension['sort_order'])
                    ? (int) $extension['sort_order']
                    : ($extensionIndex + 1),
            ];
        }

        return $normalized;
    }

    private function normalizeOfficials(mixed $officials): array
    {
        if (! is_array($officials)) {
            return [];
        }

        return collect($officials)
            ->filter(fn ($o) => is_array($o) && ! empty($o['name']))
            ->values()
            ->map(function ($o) {
                $masterId = isset($o['official_id']) ? (int) $o['official_id'] : null;

                // Linked rows take their snapshot from the master server-side; the
                // client copy is ignored. Unlinked/legacy rows keep submitted values.
                $snapshot = $masterId ? $this->officialUserService->findSnapshot($masterId) : null;
                $source = $snapshot ?? $o;

                return [
                    'official_id' => $masterId,
                    'type' => $this->normalizeNullableString($source['type'] ?? null),
                    'name' => trim((string) ($source['name'] ?? '')),
                    'contact_number' => $this->normalizeNullableString($source['contact_number'] ?? null),
                    'nationality' => $this->normalizeNullableString($source['nationality'] ?? null),
                    'passport_number' => $this->normalizeNullableString($source['passport_number'] ?? null),
                    'gender' => $this->normalizeNullableString($source['gender'] ?? null),
                    'date_of_birth' => $this->normalizeNullableString($source['date_of_birth'] ?? null),
                    'passport_issue_date' => $this->normalizeNullableString($source['passport_issue_date'] ?? null),
                    'passport_expiry_date' => $this->normalizeNullableString($source['passport_expiry_date'] ?? null),
                    'passport_place_of_issue' => $this->normalizeNullableString($source['passport_place_of_issue'] ?? null),
                    'place_of_birth' => $this->normalizeNullableString($source['place_of_birth'] ?? null),
                ];
            })
            ->toArray();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }
}
