<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestRoom;
use App\Models\ManifestSharingGroup;
use App\Models\ModelFile;
use App\Models\PackageOfficial;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManifestService
{
    public function get()
    {
        $data = Manifest::get();

        return $data;
    }

    public function getForDataTable(array $filters = [])
    {
        $authUser = auth()->user();
        $authCountryId = $authUser?->branch?->country_id ? (int) $authUser->branch->country_id : null;
        $isCountryScopedRole =
            $authUser !== null
            && (method_exists($authUser, 'hasRole'))
            && ($authUser->hasRole('admin') || $authUser->hasRole('sales'));

        $data = Manifest::with('package')
            ->withCount([
                'members as members_count' => function ($query) {
                    $query->whereNull('package_official_id');
                },
            ])
            ->when($isCountryScopedRole && $authCountryId !== null, function ($query) use ($authCountryId) {
                $query->whereHas('package', function ($packageQuery) use ($authCountryId) {
                    $packageQuery->where('country_id', $authCountryId);
                });
            })
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('manifest_number', 'like', "%{$value}%")
                        ->orWhereHas('package', function ($pq) use ($value) {
                            $pq->where('name', 'like', "%{$value}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, function ($q, $value) {
                $q->whereHas('package', function ($packageQuery) use ($value) {
                    $packageQuery->where('status', $value);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'package_id' => $q->package_id,
                    'package_name' => $q->package?->name,
                    'manifest_number' => $q->manifest_number,
                    'departure_date' => $q->package?->departure_date_formatted,
                    'return_date' => $q->package?->return_date_formatted,
                    'status' => $q->package?->status,
                    'members_count' => $q->members_count,
                    'created_at' => $q->created_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function getForFilter()
    {
        $data = Manifest::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->manifest_number,
            ];
        });

        return $data;
    }

    public function getForFilterByName()
    {
        $data = Manifest::get()->map(function ($q) {
            return [
                'value' => $q->manifest_number,
                'label' => $q->manifest_number,
            ];
        });

        return $data;
    }

    public function store(array $data): Manifest
    {
        return DB::transaction(function () use ($data) {
            $manifestAttributes = [
                'package_id' => $data['package_id'],
                'manifest_number' => NumberGenerator::generate('manifest'),
                'notes' => $data['notes'] ?? null,
            ];

            if (Schema::hasColumn('manifests', 'in_charge_official_id')) {
                $manifestAttributes['in_charge_official_id'] = $data['in_charge_official_id'] ?? null;
            }

            $manifest = Manifest::create($manifestAttributes);

            $this->syncPackageStatus($manifest, $data['status'] ?? null);

            $this->syncMembers($manifest, $data['members'] ?? []);
            $this->syncManifestDocuments($manifest, $data['documents'] ?? []);
            $this->syncRooms($manifest, $data['rooms'] ?? []);

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Manifest created successfully #'.$manifest->id);

            return $manifest;
        });
    }

    public function getForEditShow($id): array
    {
        $manifest = Manifest::with([
            'package.accommodations',
            'package.flights',
            'package.officials',
            'inChargeOfficial',
            'members.sharingGroup',
            'members.packageOfficial',
            'members.collectionItem',
            'members.files',
            'members.confirmationMember.confirmation.enquiry',
            'members.confirmationMember.customer.user',
            'members.confirmationMember.receiptAllocations.receipt',
            'members.confirmationMember.quotationItems.quotation.quotationExtensions',
            'members.confirmationMember.quotationItems.quotation.quotationItems',
            'members.confirmationMember.quotationItems.invoices.quotationItems',
            'members.confirmationMember.quotationItems.invoices.receipt',
            'rooms.roomMembers.member',
            'files',
            'manifestSharingGroups.customerConfirmation',
            'manifestSharingGroups.members.confirmationMember.confirmation.enquiry',
            'manifestSharingGroups.members.confirmationMember.customer.user',
        ])->findOrFail($id);

        $members = $manifest->members
            ->sort(function ($left, $right) {
                $leftOfficial = $left->package_official_id !== null ? 1 : 0;
                $rightOfficial = $right->package_official_id !== null ? 1 : 0;

                if ($leftOfficial !== $rightOfficial) {
                    return $leftOfficial <=> $rightOfficial;
                }

                $leftGroupSort = (int) ($left->sharingGroup?->sort_order ?? PHP_INT_MAX);
                $rightGroupSort = (int) ($right->sharingGroup?->sort_order ?? PHP_INT_MAX);

                if ($leftGroupSort !== $rightGroupSort) {
                    return $leftGroupSort <=> $rightGroupSort;
                }

                $leftMemberSort = (int) ($left->sort_order ?? PHP_INT_MAX);
                $rightMemberSort = (int) ($right->sort_order ?? PHP_INT_MAX);

                if ($leftMemberSort !== $rightMemberSort) {
                    return $leftMemberSort <=> $rightMemberSort;
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->values()
            ->map(function ($member, $index) use ($manifest) {
                $confirmationMember = $member->confirmationMember;
                $confirmation = $confirmationMember?->confirmation;
                $enquiryCreatedAt = $confirmation?->enquiry?->created_at;
                $customer = $confirmationMember?->customer;
                $user = $customer?->user;
                $packageOfficial = $member->packageOfficial;
                $sharingPlan = $member->sharing_plan ?? $confirmationMember?->sharing_plan;
                $collectionItem = $member->collectionItem;
                $packagePrice = $this->getPackagePriceForSharingPlan($manifest->package, $sharingPlan);
                $financialSnapshot = $this->buildMemberFinancialSnapshot(
                    $confirmationMember,
                    $packagePrice,
                    $member->package_official_id !== null,
                );

                return [
                    'id' => $member->id,
                    'sn' => $index + 1,
                    'customer_id' => $customer?->id,
                    'customer_confirmation_member_id' => $member->customer_confirmation_member_id,
                    'package_official_id' => $member->package_official_id,
                    'customer_confirmation_id' => $confirmationMember?->customer_confirmation_id,
                    'customer_confirmation_number' => $confirmation?->number,
                    'is_official' => $member->package_official_id !== null,
                    'customer_name' => $member->name ?? $packageOfficial?->name ?? $user?->name,
                    'name_as_per_passport' => $member->name ?? $packageOfficial?->name ?? $user?->name,
                    'arabic_name' => $member->arabic_name,
                    'contact_no' => $member->contact_number ?? $packageOfficial?->contact_number ?? $user?->contact,
                    'relationship' => $member->relationship ?? $confirmationMember?->relationship,
                    'group_relationship' => $member->sharingGroup?->group_relationship,
                    'group_remarks' => $member->sharingGroup?->remarks,
                    'sharing_plan' => $sharingPlan,
                    'course_1' => (bool) ($collectionItem?->course_1 ?? false),
                    'course_2' => (bool) ($collectionItem?->course_2 ?? false),
                    'lanyard' => (bool) ($collectionItem?->lanyard ?? false),
                    'luggage_tag' => (bool) ($collectionItem?->luggage_tag ?? false),
                    'cabin_tag' => (bool) ($collectionItem?->cabin_tag ?? false),
                    'passport_cover' => (bool) ($collectionItem?->passport_cover ?? false),
                    'umrah_guidebook' => (bool) ($collectionItem?->umrah_guidebook ?? false),
                    'sling_bag' => (bool) ($collectionItem?->sling_bag ?? false),
                    'cabin_size_luggage' => (bool) ($collectionItem?->cabin_size_luggage ?? false),
                    'umrah_essentials' => (bool) ($collectionItem?->umrah_essentials ?? false),
                    'manifest_sharing_group_id' => $member->manifest_sharing_group_id,
                    'sharing_group_id' => $member->manifest_sharing_group_id,
                    'sharing_group_key' => $member->manifest_sharing_group_id
                        ? 'group-'.$member->manifest_sharing_group_id
                        : 'group-'.$member->id,
                    'group_sort_order' => $member->sharingGroup?->sort_order,
                    'sort_order' => $member->sort_order,
                    'package_category' => $confirmation?->package_category,
                    'date_of_sign_up' => ($enquiryCreatedAt ?? $confirmation?->created_at)?->translatedFormat('d F Y'),
                    'package_price' => $packagePrice,
                    'discount' => $financialSnapshot['discount'],
                    'date_of_deposit_payment' => $financialSnapshot['date_of_deposit_payment'],
                    'deposit_payment' => $financialSnapshot['deposit_payment'],
                    'date_of_second_payment' => $financialSnapshot['date_of_second_payment'],
                    'second_payment' => $financialSnapshot['second_payment'],
                    'balance_due' => $financialSnapshot['balance_due'],
                    'is_first_time_umrah' => $member->first_time_umrah ?? $customer?->first_time_umrah,
                    'passport_number' => $member->passport_number ?? $packageOfficial?->passport_number ?? $customer?->passport_number,
                    'nationality' => $member->nationality ?? $packageOfficial?->nationality ?? $customer?->nationality,
                    'gender' => $member->gender ?? $packageOfficial?->gender ?? $customer?->gender,
                    'date_of_issue' => $this->formatDateForUi($member->passport_issue_date ?? $packageOfficial?->passport_issue_date ?? $customer?->passport_issue_date),
                    'date_of_expiry' => $this->formatDateForUi($member->passport_expiry_date ?? $packageOfficial?->passport_expiry_date ?? $customer?->passport_expiry_date),
                    'issue_place' => $member->passport_place_of_issue ?? $packageOfficial?->passport_place_of_issue ?? $customer?->passport_place_of_issue,
                    'birth_place' => $member->place_of_birth ?? $packageOfficial?->place_of_birth ?? $customer?->place_of_birth,
                    'date_of_birth' => $this->formatDateForUi($member->date_of_birth ?? $packageOfficial?->date_of_birth ?? $customer?->date_of_birth),
                    'age' => ($member->date_of_birth ?? $packageOfficial?->date_of_birth ?? $customer?->date_of_birth)?->age,
                    'address' => $member->address ?? $customer?->address,
                    'first_time_umrah' => $member->first_time_umrah ?? $customer?->first_time_umrah,
                    'has_chronic_disease' => $member->has_chronic_disease ?? $customer?->has_chronic_disease,
                    'is_using_wheelchair' => $member->is_using_wheelchair ?? $customer?->is_using_wheelchair,
                    'chronic_disease_details' => $member->chronic_disease_details ?? $customer?->chronic_disease_details,
                    'passport_path' => $member->passport_path ?? $customer?->passport_path,
                    'photo_path' => $member->photo_path ?? $customer?->photo_path,
                    'remarks' => $member->remarks,
                    'receipt_documents' => $member->files
                        ->where('field', 'receipt')
                        ->map(fn (ModelFile $file) => [
                            'id' => $file->id,
                            'file_name' => $file->file_name,
                            'file_path' => $file->file_path,
                        ])
                        ->values()
                        ->toArray(),
                    'status' => $confirmationMember?->status ?? 'draft',
                ];
            })
            ->toArray();

        $documents = $this->buildManifestDocumentPayload($manifest);

        $roomLists = $this->buildRoomListsFromRooms($manifest, $members);

        if ($roomLists === []) {
            $roomLists = $this->ensureRoomLists(
                null,
                [],
                $members,
                $manifest->package?->accommodations?->toArray() ?? [],
            );
        }
        $airlineList = $this->ensureFlatList(null, $members);

        $legacyRooms = $manifest->rooms->map(function ($room) {
            return [
                'id' => $room->id,
                'sort_order' => $room->sort_order,
                'location' => $room->location,
                'group_relationship' => $room->group_relationship,
                'room_label' => $room->room_label,
                'room_number' => $room->room_number,
                'room_type' => $room->room_type,
                'bed_type' => $room->bed_type,
                'capacity' => $room->capacity,
                'status' => $room->status,
                'meal' => $room->meal,
                'number_of_beds_checked' => (bool) $room->number_of_beds_checked,
                'remarks' => $room->remarks,
                'members' => $room->roomMembers->map(function ($roomMember) {
                    $member = $roomMember->member?->confirmationMember;
                    $customer = $member?->customer;
                    $official = $roomMember->member?->packageOfficial;

                    return [
                        'id' => $roomMember->id,
                        'manifest_member_id' => $roomMember->manifest_member_id,
                        'member_name' => $roomMember->member?->name ?? $official?->name ?? $customer?->user?->name,
                        'role_in_room' => $member?->relationship,
                        'sort_order' => $roomMember->sort_order,
                        'remarks' => $roomMember->remarks,
                        'customer_confirmation_member_id' => $member?->id,
                        'customer_id' => $customer?->id,
                    ];
                })->toArray(),
            ];
        })->toArray();

        $legacySharingGroups = $manifest->manifestSharingGroups->map(function ($manifestSharingGroup) {
            return [
                'id' => $manifestSharingGroup->id,
                'customer_confirmation_id' => $manifestSharingGroup->customer_confirmation_id,
                'sort_order' => $manifestSharingGroup->sort_order,
                'group_relationship' => $manifestSharingGroup->group_relationship,
                'remarks' => $manifestSharingGroup->remarks,
                'members' => $manifestSharingGroup->members->map(function ($member) {
                    $confirmationMember = $member->confirmationMember;

                    return [
                        'id' => $member->id,
                        'customer_confirmation_member_id' => $member->customer_confirmation_member_id,
                        'package_official_id' => $member->package_official_id,
                        'role_in_group' => $member->relationship ?? $confirmationMember?->relationship,
                        'sort_order' => $member->sort_order,
                        'sharing_plan' => $member->sharing_plan,
                        'remarks' => $member->remarks,
                        'status' => $confirmationMember?->status,
                        'customer_name' => $confirmationMember?->customer?->user?->name,
                        'customer_id' => $confirmationMember?->customer_id,
                    ];
                })->toArray(),
            ];
        })->toArray();

        $canonicalManifest = [
            'id' => $manifest->id,
            'package_id' => $manifest->package_id,
            'in_charge_official_id' => $manifest->in_charge_official_id,
            'manifest_number' => $manifest->manifest_number,
            'status' => $manifest->package?->status,
            'notes' => $manifest->notes,
        ];

        $canonicalSharingGroups = array_map(function (array $group): array {
            return [
                'id' => $group['id'] ?? null,
                'customer_confirmation_id' => $group['customer_confirmation_id'] ?? null,
                'sort_order' => $group['sort_order'] ?? null,
                'group_relationship' => $group['group_relationship'] ?? $group['relation'] ?? null,
                'remarks' => $group['remarks'] ?? null,
                'members' => array_map(function (array $member): array {
                    return [
                        'id' => $member['id'] ?? null,
                        'customer_confirmation_member_id' => $member['customer_confirmation_member_id'] ?? null,
                        'package_official_id' => $member['package_official_id'] ?? null,
                        'relationship' => $member['relationship'] ?? $member['role'] ?? $member['role_in_group'] ?? null,
                        'sharing_plan' => $member['sharing_plan'] ?? null,
                        'sort_order' => $member['sort_order'] ?? null,
                        'remarks' => $member['remarks'] ?? null,
                        'status' => $member['status'] ?? null,
                    ];
                }, $group['members'] ?? []),
            ];
        }, $legacySharingGroups);

        $canonicalRooms = array_map(function (array $room): array {
            return [
                'id' => $room['id'] ?? null,
                'location' => $room['location'] ?? null,
                'sort_order' => $room['sort_order'] ?? null,
                'group_relationship' => $room['group_relationship'] ?? $room['relationship'] ?? null,
                'room_label' => $room['room_label'] ?? null,
                'room_number' => $room['room_number'] ?? null,
                'room_type' => $room['room_type'] ?? null,
                'bed_type' => $room['bed_type'] ?? null,
                'capacity' => $room['capacity'] ?? null,
                'meal' => $room['meal'] ?? null,
                'number_of_beds_checked' => $room['number_of_beds_checked'] ?? false,
                'remarks' => $room['remarks'] ?? null,
                'members' => array_map(function (array $member): array {
                    return [
                        'id' => $member['id'] ?? null,
                        'manifest_member_id' => $member['manifest_member_id'] ?? $member['id'] ?? null,
                        'customer_confirmation_member_id' => $member['customer_confirmation_member_id'] ?? null,
                        'package_official_id' => $member['package_official_id'] ?? null,
                        'sort_order' => $member['sort_order'] ?? null,
                        'remarks' => $member['remarks'] ?? null,
                    ];
                }, $room['members'] ?? []),
            ];
        }, $legacyRooms);

        return [
            'id' => $manifest->id,
            'package_id' => $manifest->package_id,
            'in_charge_official_id' => $manifest->in_charge_official_id,
            'in_charge_official_name' => $manifest->inChargeOfficial?->name,
            'in_charge_official_contact_number' => $manifest->inChargeOfficial?->contact_number,
            'package_number' => $manifest->package?->package_number,
            'package_name' => $manifest->package?->name,
            'manifest_number' => $manifest->manifest_number,
            'departure_date' => $manifest->package?->departure_date_formatted,
            'return_date' => $manifest->package?->return_date_formatted,
            'package_accommodations' => $manifest->package?->accommodations
                ?->map(function ($accommodation) {
                    return [
                        'location' => $accommodation->location,
                        'hotel_name' => $accommodation->hotel_name,
                        'check_in_formatted' => $accommodation->check_in_formatted,
                    ];
                })
                ->values()
                ->toArray() ?? [],
            'notes' => $manifest->notes,
            'status' => $manifest->package?->status,
            'members' => $members,
            'manifest_member_receipts' => collect($members)
                ->map(function (array $member): array {
                    return [
                        'manifest_member_id' => $member['id'] ?? null,
                        'customer_confirmation_member_id' => $member['customer_confirmation_member_id'] ?? null,
                        'receipt_documents' => $member['receipt_documents'] ?? [],
                    ];
                })
                ->values()
                ->toArray(),
            'roomLists' => $roomLists,
            'airlineList' => $airlineList,
            'documents' => $documents,
            'rooms' => $legacyRooms,
            'sharing_groups' => $legacySharingGroups,
            'manifest' => $canonicalManifest,
            'manifest_sharing_groups' => $canonicalSharingGroups,
            'manifest_rooms' => $canonicalRooms,
        ];
    }

    public function update(array $data, int $id): Manifest
    {
        return DB::transaction(function () use ($data, $id) {
            $manifest = Manifest::findOrFail($id);

            $manifestAttributes = [
                'package_id' => $data['package_id'] ?? $manifest->package_id,
                'notes' => $data['notes'] ?? $manifest->notes,
            ];

            if (
                Schema::hasColumn('manifests', 'in_charge_official_id')
                && array_key_exists('in_charge_official_id', $data)
            ) {
                $manifestAttributes['in_charge_official_id'] = $data['in_charge_official_id'];
            }

            $manifest->update($manifestAttributes);

            $this->syncPackageStatus($manifest, $data['status'] ?? null);

            if (isset($data['members'])) {
                $this->syncMembers($manifest, $data['members']);
            }

            if (isset($data['documents'])) {
                $this->syncManifestDocuments($manifest, $data['documents']);
            }

            if (isset($data['rooms'])) {
                $this->syncRooms($manifest, $data['rooms']);
            }

            $manifest = $manifest->fresh();

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Manifest updated successfully #'.$manifest->id);

            return $manifest;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $memberReceiptDocuments
     */
    public function syncMemberReceiptDocumentsSection(Manifest $manifest, array $memberReceiptDocuments): void
    {
        DB::transaction(function () use ($manifest, $memberReceiptDocuments): void {
            $manifest->loadMissing('members.files');

            $membersById = $manifest->members
                ->keyBy(fn (ManifestMember $member) => (int) $member->id);

            $membersByConfirmationMemberId = $manifest->members
                ->filter(fn (ManifestMember $member) => $member->customer_confirmation_member_id !== null)
                ->keyBy(fn (ManifestMember $member) => (int) $member->customer_confirmation_member_id);

            foreach ($memberReceiptDocuments as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $manifestMemberId = isset($item['manifest_member_id'])
                    ? (int) $item['manifest_member_id']
                    : 0;

                $confirmationMemberId = isset($item['customer_confirmation_member_id'])
                    ? (int) $item['customer_confirmation_member_id']
                    : 0;

                /** @var ManifestMember|null $manifestMember */
                $manifestMember = $manifestMemberId > 0
                    ? $membersById->get($manifestMemberId)
                    : null;

                if (! $manifestMember && $confirmationMemberId > 0) {
                    $manifestMember = $membersByConfirmationMemberId->get($confirmationMemberId);
                }

                if (! $manifestMember) {
                    continue;
                }

                $receiptDocuments = $item['receipt_documents'] ?? [];

                if (! is_array($receiptDocuments)) {
                    continue;
                }

                $this->persistMemberReceiptDocuments($manifestMember, $receiptDocuments);
            }
        });
    }

    public function delete($id)
    {
        $manifest = Manifest::find($id);
        if (! $manifest) {
            return false;
        }

        activity()
            ->performedOn($manifest)
            ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
            ->log('Manifest deleted successfully #'.$manifest->id);

        return $manifest->delete();
    }

    /**
     * Add a single room to a manifest.
     */
    public function addRoom(int $manifestId, array $data): ManifestRoom
    {
        return DB::transaction(function () use ($manifestId, $data) {
            $manifest = Manifest::findOrFail($manifestId);

            $room = $manifest->rooms()->create([
                'sort_order' => $data['sort_order'] ?? (((int) $manifest->rooms()->max('sort_order')) + 1),
                'location' => $data['location'] ?? null,
                'group_relationship' => $data['group_relationship'] ?? $data['relationship'] ?? null,
                'room_label' => $data['room_label'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'bed_type' => $data['bed_type'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'meal' => $data['meal'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            if (! empty($data['members'])) {
                foreach ($data['members'] as $index => $member) {
                    $manifestMemberId = isset($member['manifest_member_id'])
                        ? (int) $member['manifest_member_id']
                        : 0;

                    $room->roomMembers()->create([
                        'manifest_member_id' => $manifestMemberId,
                        'sort_order' => (int) ($member['sort_order'] ?? ($index + 1)),
                        'remarks' => $member['remarks'] ?? null,
                    ]);
                }
            }

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Room added to manifest #'.$manifest->id);

            return $room->load('roomMembers.member');
        });
    }

    /**
     * Update a room and its members.
     */
    public function updateRoom(int $roomId, array $data): ManifestRoom
    {
        return DB::transaction(function () use ($roomId, $data) {
            $room = ManifestRoom::findOrFail($roomId);

            $room->update([
                'sort_order' => $data['sort_order'] ?? $room->sort_order,
                'location' => $data['location'] ?? $room->location,
                'group_relationship' => $data['group_relationship'] ?? $data['relationship'] ?? $room->group_relationship,
                'room_label' => $data['room_label'] ?? $room->room_label,
                'room_number' => $data['room_number'] ?? $room->room_number,
                'room_type' => $data['room_type'] ?? $room->room_type,
                'bed_type' => $data['bed_type'] ?? $room->bed_type,
                'capacity' => $data['capacity'] ?? $room->capacity,
                'status' => $data['status'] ?? $room->status,
                'meal' => $data['meal'] ?? $room->meal,
                'remarks' => $data['remarks'] ?? $room->remarks,
            ]);

            if (isset($data['members'])) {
                $room->roomMembers()->delete();
                foreach ($data['members'] as $index => $member) {
                    $manifestMemberId = isset($member['manifest_member_id'])
                        ? (int) $member['manifest_member_id']
                        : 0;

                    $room->roomMembers()->create([
                        'manifest_member_id' => $manifestMemberId,
                        'sort_order' => (int) ($member['sort_order'] ?? ($index + 1)),
                        'remarks' => $member['remarks'] ?? null,
                    ]);
                }
            }

            activity()
                ->performedOn($room->manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $room->manifest_id])
                ->log('Room updated in manifest #'.$room->manifest_id);

            return $room->fresh()->load('roomMembers.member');
        });
    }

    /**
     * Delete a room and its members.
     */
    public function deleteRoom(int $roomId): bool
    {
        $room = ManifestRoom::find($roomId);
        if (! $room) {
            return false;
        }

        $manifestId = $room->manifest_id;
        $room->roomMembers()->delete();
        $room->delete();

        activity()
            ->performedOn(Manifest::find($manifestId))
            ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifestId])
            ->log('Room deleted from manifest #'.$manifestId);

        return true;
    }

    /**
     * Attach sharing groups to a manifest from customer confirmations.
     */
    public function attachSharingGroup(int $manifestId, int $customerConfirmationId): ManifestSharingGroup
    {
        $manifest = Manifest::findOrFail($manifestId);

        $msg = ManifestSharingGroup::firstOrCreate([
            'manifest_id' => $manifestId,
            'customer_confirmation_id' => $customerConfirmationId,
        ], [
            'sort_order' => ((int) ManifestSharingGroup::query()->where('manifest_id', $manifestId)->max('sort_order')) + 1,
            'group_relationship' => null,
            'remarks' => null,
        ]);

        activity()
            ->performedOn($manifest)
            ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifestId])
            ->log('Confirmation #'.$customerConfirmationId.' attached to manifest #'.$manifestId);

        return $msg;
    }

    /**
     * Detach a sharing group from a manifest.
     */
    public function detachSharingGroup(int $manifestId, int $manifestSharingGroupId): bool
    {
        $deleted = ManifestSharingGroup::where('manifest_id', $manifestId)
            ->where('id', $manifestSharingGroupId)
            ->delete();

        if ($deleted) {
            activity()
                ->performedOn(Manifest::find($manifestId))
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifestId])
                ->log('Manifest sharing group #'.$manifestSharingGroupId.' detached from manifest #'.$manifestId);
        }

        return $deleted > 0;
    }

    /**
     * Get customer confirmations with member details for the manifest form.
     * Includes passport, date-of-birth, and other member-relevant data.
     *
     * @param  int|null  $packageId  Filter by package if provided
     */
    public function getCustomerConfirmationsForManifest(?int $packageId = null): array
    {
        return CustomerConfirmation::with([
            'members.customer.user',
            'enquiry',
            'package',
        ])
            ->whereNotNull('package_id')
            ->when($packageId, function ($q) use ($packageId) {
                $q->where('package_id', $packageId);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerConfirmation $confirmation) {
                $leader = $confirmation->members->firstWhere('is_leader', true);

                $activeMembers = $confirmation->members
                    ->filter(fn ($member) => ($member->status ?? 'draft') !== 'cancelled')
                    ->values();

                return [
                    'id' => $confirmation->id,
                    'customer_confirmation_number' => $confirmation->number,
                    'package_id' => $confirmation->package_id,
                    'package_room_type' => $confirmation->package?->room_type ?? null,
                    'enquiry_id' => $confirmation->enquiry_id,
                    'enquiry_type' => $confirmation->enquiry?->type ? ucfirst($confirmation->enquiry->type) : null,
                    'enquiry_status' => $confirmation->enquiry?->status?->label() ?? null,
                    'leader_name' => $leader?->customer?->user?->name ?? '-',
                    'leader_email' => $leader?->customer?->user?->email ?? '-',
                    'leader_contact' => $leader?->customer?->user?->contact ?? '-',
                    'leader_customer_number' => $leader?->customer?->customer_number ?? '-',
                    'member_count' => $activeMembers->count(),
                    'created_at' => $confirmation->created_at?->translatedFormat('d F Y'),
                    'members' => $activeMembers->map(function ($member) {
                        $customer = $member->customer;
                        $user = $customer?->user;

                        return [
                            'id' => $member->id,
                            'customer_id' => $member->customer_id,
                            'customer_confirmation_number' => $confirmation->number,
                            'is_leader' => $member->is_leader,
                            'status' => $member->status ?? 'draft',
                            'sharing_plan' => $member->sharing_plan,
                            'relationship' => $member->relationship,
                            'name' => $user?->name ?? '-',
                            'email' => $user?->email ?? '-',
                            'contact' => $user?->contact ?? '-',
                            'customer_number' => $customer?->customer_number ?? '-',
                            'nric_number' => $customer?->nric_number ?? '-',
                            'passport_number' => $customer?->passport_number ?? '-',
                            'passport_issue_date' => $customer?->passport_issue_date_formatted ?? '',
                            'passport_expiry_date' => $customer?->passport_expiry_date_formatted ?? '',
                            'passport_place_of_issue' => $customer?->passport_place_of_issue ?? '',
                            'date_of_birth' => $customer?->date_of_birth_formatted ?? '',
                            'age' => $customer?->date_of_birth ? $customer->date_of_birth->age : null,
                        ];
                    })->values()->all(),
                ];
            })
            ->filter(fn (array $confirmation) => ($confirmation['member_count'] ?? 0) > 0)
            ->values()
            ->all();
    }

    /**
     * Parse date fields in-place from various formats to Y-m-d.
     */
    /**
     * Sync members for a manifest while preserving existing IDs.
     *
     * Accepts either a flat array of members or a grouped Record<groupId, MemberSchema[]> from the frontend.
     */
    private function syncMembers(Manifest $manifest, array $members): void
    {
        $manifest->loadMissing('members.collectionItem', 'members.files', 'manifestSharingGroups');

        $existingMembersById = $manifest->members
            ->keyBy(fn (ManifestMember $member) => (int) $member->id);

        $existingMembersByConfirmationMemberId = $manifest->members
            ->filter(fn (ManifestMember $member) => $member->customer_confirmation_member_id !== null)
            ->keyBy(fn (ManifestMember $member) => (int) $member->customer_confirmation_member_id);

        $existingMembersByOfficialId = $manifest->members
            ->filter(fn (ManifestMember $member) => $member->package_official_id !== null)
            ->keyBy(fn (ManifestMember $member) => (int) $member->package_official_id);

        $existingGroupsById = $manifest->manifestSharingGroups
            ->keyBy(fn (ManifestSharingGroup $group) => (int) $group->id);

        $flatMembers = collect($this->flattenGroupedData($members))
            ->values()
            ->map(function (array $member, int $index): array {
                $member['_original_index'] = $index;

                return $member;
            })
            ->values()
            ->all();

        $confirmationMemberIds = collect($flatMembers)
            ->pluck('customer_confirmation_member_id')
            ->filter(fn ($value) => ! empty($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $confirmationMembers = $confirmationMemberIds->isEmpty()
            ? collect()
            : CustomerConfirmationMember::query()
                ->whereIn('id', $confirmationMemberIds->all())
                ->get(['id', 'customer_confirmation_id', 'sharing_plan']);

        $confirmationIdMap = $confirmationMembers
            ->pluck('customer_confirmation_id', 'id')
            ->map(fn ($value) => (int) $value);

        $confirmationSharingPlanMap = $confirmationMembers
            ->pluck('sharing_plan', 'id')
            ->map(fn ($value) => is_string($value) ? strtolower(trim($value)) : '');

        $groupedMembers = [];
        $groupSizes = [];
        $groupBuckets = [];
        $groupKeyCounter = [];
        $groupTypeByKey = [];

        foreach ($flatMembers as $index => $member) {
            $groupKey = isset($member['sharing_group_key']) && is_string($member['sharing_group_key'])
                ? trim($member['sharing_group_key'])
                : '';

            $confirmationMemberId = ! empty($member['customer_confirmation_member_id'])
                ? (int) $member['customer_confirmation_member_id']
                : null;

            $confirmationId = ! empty($member['customer_confirmation_id'])
                ? (int) $member['customer_confirmation_id']
                : ($confirmationMemberId ? (int) ($confirmationIdMap->get($confirmationMemberId) ?? 0) : 0);

            $sharingPlan = isset($member['sharing_plan']) && is_string($member['sharing_plan'])
                ? strtolower(trim($member['sharing_plan']))
                : '';

            if ($sharingPlan === '' && $confirmationMemberId) {
                $sharingPlan = (string) ($confirmationSharingPlanMap->get($confirmationMemberId) ?? '');
            }

            $memberType = ! empty($member['package_official_id']) ? 'official' : 'member';

            $capacity = $this->capacityFromSharingPlan($sharingPlan !== '' ? $sharingPlan : null);
            $bucketKey = $confirmationId > 0 && $sharingPlan !== ''
                ? $confirmationId.'|'.$sharingPlan.'|'.$memberType
                : null;

            $isExplicitKey = $groupKey !== '' && ! Str::startsWith($groupKey, 'solo-');

            if (! $isExplicitKey && $bucketKey !== null) {
                $candidateKeys = $groupBuckets[$bucketKey] ?? [];
                $selectedKey = null;

                foreach ($candidateKeys as $candidateKey) {
                    if (($groupSizes[$candidateKey] ?? 0) < $capacity) {
                        $selectedKey = $candidateKey;
                        break;
                    }
                }

                if ($selectedKey === null) {
                    $groupKeyCounter[$bucketKey] = ($groupKeyCounter[$bucketKey] ?? 0) + 1;
                    $selectedKey = 'auto-'.$bucketKey.'-'.$groupKeyCounter[$bucketKey];
                    $groupBuckets[$bucketKey][] = $selectedKey;
                }

                $groupKey = $selectedKey;
            }

            if ($groupKey === '') {
                $groupId = $member['manifest_sharing_group_id']
                    ?? $member['sharing_group_id']
                    ?? null;

                if (! empty($groupId)) {
                    $groupKey = 'group-'.((int) $groupId);
                } else {
                    $groupKey = 'solo-'.((int) ($member['customer_confirmation_member_id'] ?? $member['customer_id'] ?? ($index + 1)));
                }
            }

            if (isset($groupTypeByKey[$groupKey]) && $groupTypeByKey[$groupKey] !== $memberType) {
                $groupKey = $groupKey.'|'.$memberType;
            }

            $groupTypeByKey[$groupKey] = $memberType;

            if ($bucketKey !== null && ! in_array($groupKey, $groupBuckets[$bucketKey] ?? [], true)) {
                $groupBuckets[$bucketKey][] = $groupKey;
            }

            $groupSizes[$groupKey] = ($groupSizes[$groupKey] ?? 0) + 1;

            $member['sharing_group_key'] = $groupKey;

            $groupedMembers[$groupKey][] = $member;
        }

        $nonOfficialGroups = [];
        $officialGroups = [];

        foreach ($groupedMembers as $groupKey => $groupMembers) {
            // Preserve the exact incoming sequence from main tab for member order inside each group.
            $sortedGroupMembers = array_values($groupMembers);

            $isOfficialGroup = collect($sortedGroupMembers)
                ->every(fn (array $member) => ! empty($member['package_official_id']));

            $groupPayload = [
                'key' => $groupKey,
                'members' => $sortedGroupMembers,
                'group_sort_order' => (int) ($sortedGroupMembers[0]['group_sort_order'] ?? PHP_INT_MAX),
                'original_index' => (int) ($sortedGroupMembers[0]['_original_index'] ?? PHP_INT_MAX),
            ];

            if ($isOfficialGroup) {
                $officialGroups[] = $groupPayload;
            } else {
                $nonOfficialGroups[] = $groupPayload;
            }
        }

        $sortGroupPayload = function (array $left, array $right): int {
            $sortOrder = ($left['group_sort_order'] ?? PHP_INT_MAX) <=> ($right['group_sort_order'] ?? PHP_INT_MAX);

            if ($sortOrder !== 0) {
                return $sortOrder;
            }

            return ($left['original_index'] ?? PHP_INT_MAX) <=> ($right['original_index'] ?? PHP_INT_MAX);
        };

        usort($nonOfficialGroups, $sortGroupPayload);
        usort($officialGroups, $sortGroupPayload);

        $orderedGroups = [...$nonOfficialGroups, ...$officialGroups];

        $groupSortOrder = 1;
        $retainedGroupIds = [];
        $retainedMemberIds = [];

        foreach ($orderedGroups as $groupPayload) {
            $groupMembers = $groupPayload['members'];
            $firstMember = $groupMembers[0] ?? [];

            $groupCustomerConfirmationId = null;

            if (! empty($firstMember['customer_confirmation_id'])) {
                $groupCustomerConfirmationId = (int) $firstMember['customer_confirmation_id'];
            } elseif (! empty($firstMember['customer_confirmation_member_id'])) {
                $groupCustomerConfirmationId = CustomerConfirmationMember::query()
                    ->whereKey((int) $firstMember['customer_confirmation_member_id'])
                    ->value('customer_confirmation_id');
            }

            $incomingGroupId = isset($firstMember['manifest_sharing_group_id'])
                ? (int) $firstMember['manifest_sharing_group_id']
                : 0;

            if ($incomingGroupId <= 0 && isset($firstMember['sharing_group_id'])) {
                $incomingGroupId = (int) $firstMember['sharing_group_id'];
            }

            if ($incomingGroupId <= 0 && is_string($groupPayload['key'] ?? null) && Str::startsWith((string) $groupPayload['key'], 'group-')) {
                $incomingGroupId = (int) Str::after((string) $groupPayload['key'], 'group-');
            }

            $groupAttributes = [
                'customer_confirmation_id' => $groupCustomerConfirmationId,
                'sort_order' => $groupSortOrder,
                'group_relationship' => $firstMember['group_relationship'] ?? $firstMember['relationship'] ?? null,
                'remarks' => $firstMember['group_remarks'] ?? null,
            ];

            $manifestSharingGroup = null;

            if ($incomingGroupId > 0) {
                /** @var ManifestSharingGroup|null $manifestSharingGroup */
                $manifestSharingGroup = $existingGroupsById->get($incomingGroupId);
            }

            if ($manifestSharingGroup) {
                $manifestSharingGroup->update($groupAttributes);
            } else {
                $manifestSharingGroup = $manifest->manifestSharingGroups()->create($groupAttributes);
            }

            $retainedGroupIds[] = (int) $manifestSharingGroup->id;

            foreach (array_values($groupMembers) as $memberSortOrder => $memberPayload) {
                $incomingMemberId = isset($memberPayload['id']) ? (int) $memberPayload['id'] : 0;
                $incomingConfirmationMemberId = isset($memberPayload['customer_confirmation_member_id'])
                    ? (int) $memberPayload['customer_confirmation_member_id']
                    : 0;
                $incomingOfficialId = isset($memberPayload['package_official_id'])
                    ? (int) $memberPayload['package_official_id']
                    : 0;

                $existingMember = $incomingMemberId > 0
                    ? $existingMembersById->get($incomingMemberId)
                    : null;

                if (! $existingMember && $incomingConfirmationMemberId > 0) {
                    $existingMember = $existingMembersByConfirmationMemberId->get($incomingConfirmationMemberId);
                }

                if (! $existingMember && $incomingOfficialId > 0) {
                    $existingMember = $existingMembersByOfficialId->get($incomingOfficialId);
                }

                $confirmationMemberId = isset($memberPayload['customer_confirmation_member_id'])
                    ? (int) $memberPayload['customer_confirmation_member_id']
                    : null;

                $confirmationMember = $confirmationMemberId
                    ? CustomerConfirmationMember::query()->with(['customer.user'])->find($confirmationMemberId)
                    : null;

                if ($confirmationMember) {
                    $this->syncMemberData($confirmationMember, $memberPayload);
                    $this->syncCustomerData($confirmationMember, $memberPayload);
                }

                $packageOfficial = null;
                if (! empty($memberPayload['package_official_id'])) {
                    $packageOfficial = PackageOfficial::query()->find((int) $memberPayload['package_official_id']);

                    if ($packageOfficial) {
                        $this->syncPackageOfficialData($packageOfficial, $memberPayload);
                    }
                }

                $memberAttributes = [
                    'manifest_sharing_group_id' => $manifestSharingGroup->id,
                    'customer_confirmation_member_id' => $confirmationMember?->id,
                    'package_official_id' => $packageOfficial?->id,
                    ...$this->buildManifestMemberSnapshot($confirmationMember, $memberPayload, $packageOfficial),
                    'sort_order' => $memberSortOrder + 1,
                    'remarks' => $memberPayload['remarks'] ?? null,
                ];

                if ($existingMember && (int) $existingMember->manifest_id === (int) $manifest->id) {
                    $existingMember->update($memberAttributes);
                    $savedMember = $existingMember->fresh(['collectionItem']);
                } else {
                    $savedMember = $manifest->members()->create($memberAttributes);
                }

                $retainedMemberIds[] = (int) $savedMember->id;

                $savedMember->collectionItem()->updateOrCreate(
                    [],
                    [
                        'course_1' => array_key_exists('course_1', $memberPayload)
                            ? (bool) $memberPayload['course_1']
                            : (bool) ($existingMember?->collectionItem?->course_1 ?? false),
                        'course_2' => array_key_exists('course_2', $memberPayload)
                            ? (bool) $memberPayload['course_2']
                            : (bool) ($existingMember?->collectionItem?->course_2 ?? false),
                        'lanyard' => array_key_exists('lanyard', $memberPayload)
                            ? (bool) $memberPayload['lanyard']
                            : (bool) ($existingMember?->collectionItem?->lanyard ?? false),
                        'luggage_tag' => array_key_exists('luggage_tag', $memberPayload)
                            ? (bool) $memberPayload['luggage_tag']
                            : (bool) ($existingMember?->collectionItem?->luggage_tag ?? false),
                        'cabin_tag' => array_key_exists('cabin_tag', $memberPayload)
                            ? (bool) $memberPayload['cabin_tag']
                            : (bool) ($existingMember?->collectionItem?->cabin_tag ?? false),
                        'passport_cover' => array_key_exists('passport_cover', $memberPayload)
                            ? (bool) $memberPayload['passport_cover']
                            : (bool) ($existingMember?->collectionItem?->passport_cover ?? false),
                        'umrah_guidebook' => array_key_exists('umrah_guidebook', $memberPayload)
                            ? (bool) $memberPayload['umrah_guidebook']
                            : (bool) ($existingMember?->collectionItem?->umrah_guidebook ?? false),
                        'sling_bag' => array_key_exists('sling_bag', $memberPayload)
                            ? (bool) $memberPayload['sling_bag']
                            : (bool) ($existingMember?->collectionItem?->sling_bag ?? false),
                        'cabin_size_luggage' => array_key_exists('cabin_size_luggage', $memberPayload)
                            ? (bool) $memberPayload['cabin_size_luggage']
                            : (bool) ($existingMember?->collectionItem?->cabin_size_luggage ?? false),
                        'umrah_essentials' => array_key_exists('umrah_essentials', $memberPayload)
                            ? (bool) $memberPayload['umrah_essentials']
                            : (bool) ($existingMember?->collectionItem?->umrah_essentials ?? false),
                    ],
                );
            }

            $groupSortOrder++;
        }

        $retainedMemberIds = array_values(array_unique($retainedMemberIds));
        $existingMemberIds = $existingMembersById->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $memberIdsToDelete = array_values(array_diff($existingMemberIds, $retainedMemberIds));

        if ($memberIdsToDelete !== []) {
            ModelFile::query()
                ->where('fileable_type', ManifestMember::class)
                ->whereIn('fileable_id', $memberIdsToDelete)
                ->delete();

            $manifest->members()
                ->whereIn('id', $memberIdsToDelete)
                ->delete();
        }

        $retainedGroupIds = array_values(array_unique($retainedGroupIds));
        $existingGroupIds = $existingGroupsById->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $groupIdsToDelete = array_values(array_diff($existingGroupIds, $retainedGroupIds));

        if ($groupIdsToDelete !== []) {
            $manifest->manifestSharingGroups()
                ->whereIn('id', $groupIdsToDelete)
                ->delete();
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

    /**
     * Sync rooms for a manifest while preserving existing IDs.
     */
    private function syncRooms(Manifest $manifest, array $rooms): void
    {
        $manifest->loadMissing('rooms.roomMembers', 'members');

        $existingRoomsById = $manifest->rooms
            ->keyBy(fn (ManifestRoom $room) => (int) $room->id);

        $flatRooms = $this->flattenGroupedData($rooms);

        $roomPayloads = [];

        foreach ($flatRooms as $roomIndex => $room) {
            $roomType = isset($room['room_type']) && is_string($room['room_type'])
                ? strtolower(trim($room['room_type']))
                : null;

            $bedType = isset($room['bed_type']) && is_string($room['bed_type'])
                ? strtolower(trim($room['bed_type']))
                : null;

            $roomMembers = isset($room['members']) && is_array($room['members'])
                ? array_values($room['members'])
                : [];

            $resolvedMembers = [];

            foreach ($roomMembers as $member) {
                $manifestMemberId = $this->resolveManifestMemberId($manifest, is_array($member) ? $member : []);

                if (! $manifestMemberId && is_array($member)) {
                    $isOfficialRow = ! empty($member['package_official_id']) || ! empty($member['is_official']);

                    if ($isOfficialRow) {
                        $officialId = isset($member['package_official_id'])
                            ? (int) $member['package_official_id']
                            : 0;

                        if ($officialId > 0) {
                            $manifestMemberId = (int) ($manifest->members()
                                ->where('package_official_id', $officialId)
                                ->value('id') ?? 0);
                        }

                        if (! $manifestMemberId) {
                            $memberName = trim((string) ($member['name_as_per_passport'] ?? $member['customer_name'] ?? ''));

                            if ($memberName !== '') {
                                $manifestMemberId = (int) ($manifest->members()
                                    ->whereNotNull('package_official_id')
                                    ->where('name', $memberName)
                                    ->value('id') ?? 0);
                            }
                        }
                    }
                }

                if (! $manifestMemberId) {
                    continue;
                }

                $resolvedMembers[] = [
                    ...(is_array($member) ? $member : []),
                    'manifest_member_id' => $manifestMemberId,
                ];
            }

            $roomPayloads[] = [
                'base' => $room,
                'members' => collect($resolvedMembers)
                    ->values()
                    ->sortBy(fn (array $member) => (int) ($member['sort_order'] ?? PHP_INT_MAX))
                    ->values()
                    ->all(),
                'room_type' => $roomType,
                'bed_type' => $bedType,
                'original_index' => $roomIndex,
            ];
        }

        $roomSortOrder = 1;
        $retainedRoomIds = [];

        $orderedRoomPayloads = collect($roomPayloads)
            ->sortBy(fn (array $payload) => (int) ($payload['original_index'] ?? PHP_INT_MAX))
            ->values()
            ->all();

        foreach ($orderedRoomPayloads as $payload) {
            $baseRoom = $payload['base'];
            $roomMembers = $payload['members'];

            $incomingRoomId = isset($baseRoom['id']) ? (int) $baseRoom['id'] : 0;
            if ($incomingRoomId <= 0 && isset($baseRoom['manifest_room_id'])) {
                $incomingRoomId = (int) $baseRoom['manifest_room_id'];
            }

            $roomAttributes = [
                'sort_order' => $roomSortOrder,
                'location' => $baseRoom['location'] ?? null,
                'group_relationship' => $baseRoom['group_relationship'] ?? $baseRoom['relationship'] ?? null,
                'room_label' => $baseRoom['room_label'] ?? null,
                'room_number' => $baseRoom['room_number'] ?? null,
                'room_type' => $payload['room_type'],
                'bed_type' => $payload['bed_type'],
                'capacity' => $baseRoom['capacity'] ?? ($roomMembers === [] ? null : count($roomMembers)),
                'status' => $baseRoom['status'] ?? 'pending',
                'meal' => $baseRoom['meal'] ?? null,
                'number_of_beds_checked' => (bool) ($baseRoom['number_of_beds_checked'] ?? false),
                'remarks' => $baseRoom['remarks'] ?? null,
            ];

            $savedRoom = null;

            if ($incomingRoomId > 0) {
                /** @var ManifestRoom|null $savedRoom */
                $savedRoom = $existingRoomsById->get($incomingRoomId);
            }

            if ($savedRoom) {
                $savedRoom->update($roomAttributes);
                $savedRoom->loadMissing('roomMembers');
            } else {
                $savedRoom = $manifest->rooms()->create($roomAttributes);
            }

            $retainedRoomIds[] = (int) $savedRoom->id;

            $existingRoomMembersById = $savedRoom->roomMembers
                ->keyBy('id');
            $existingRoomMembersByManifestMemberId = $savedRoom->roomMembers
                ->keyBy('manifest_member_id');
            $retainedRoomMemberIds = [];

            foreach ($roomMembers as $index => $member) {
                $manifestMemberId = ! empty($member['manifest_member_id'])
                    ? (int) $member['manifest_member_id']
                    : $this->resolveManifestMemberId($manifest, $member);

                if (! $manifestMemberId) {
                    $isOfficialRow = ! empty($member['package_official_id']) || ! empty($member['is_official']);

                    if ($isOfficialRow) {
                        $officialId = isset($member['package_official_id'])
                            ? (int) $member['package_official_id']
                            : 0;

                        if ($officialId > 0) {
                            $manifestMemberId = (int) ($manifest->members()
                                ->where('package_official_id', $officialId)
                                ->value('id') ?? 0);
                        }

                        if (! $manifestMemberId) {
                            $memberName = trim((string) ($member['name_as_per_passport'] ?? $member['customer_name'] ?? ''));

                            if ($memberName !== '') {
                                $manifestMemberId = (int) ($manifest->members()
                                    ->whereNotNull('package_official_id')
                                    ->where('name', $memberName)
                                    ->value('id') ?? 0);
                            }
                        }
                    }
                }

                if (! $manifestMemberId) {
                    continue;
                }

                $incomingRoomMemberId = isset($member['room_member_id']) ? (int) $member['room_member_id'] : 0;
                if ($incomingRoomMemberId <= 0 && isset($member['manifest_room_member_id'])) {
                    $incomingRoomMemberId = (int) $member['manifest_room_member_id'];
                }
                if ($incomingRoomMemberId <= 0 && isset($member['id'])) {
                    $incomingRoomMemberId = (int) $member['id'];
                }

                $existingRoomMember = $incomingRoomMemberId > 0
                    ? $existingRoomMembersById->get($incomingRoomMemberId)
                    : null;

                if (! $existingRoomMember) {
                    $existingRoomMember = $existingRoomMembersByManifestMemberId->get($manifestMemberId);
                }

                $roomMemberAttributes = [
                    'manifest_member_id' => $manifestMemberId,
                    'sort_order' => (int) ($member['sort_order'] ?? ($index + 1)),
                    'remarks' => $member['remarks'] ?? null,
                ];

                if ($existingRoomMember) {
                    $existingRoomMember->update($roomMemberAttributes);
                    $retainedRoomMemberIds[] = (int) $existingRoomMember->id;
                } else {
                    $createdRoomMember = $savedRoom->roomMembers()->create($roomMemberAttributes);
                    $retainedRoomMemberIds[] = (int) $createdRoomMember->id;
                }
            }

            if ($retainedRoomMemberIds === []) {
                $savedRoom->roomMembers()->delete();
            } else {
                $savedRoom->roomMembers()
                    ->whereNotIn('id', $retainedRoomMemberIds)
                    ->delete();
            }

            $roomSortOrder++;
        }

        $retainedRoomIds = array_values(array_unique($retainedRoomIds));
        $existingRoomIds = $existingRoomsById->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $roomIdsToDelete = array_values(array_diff($existingRoomIds, $retainedRoomIds));

        if ($roomIdsToDelete !== []) {
            $manifest->rooms()
                ->whereIn('id', $roomIdsToDelete)
                ->get()
                ->each(function (ManifestRoom $room): void {
                    $room->roomMembers()->delete();
                    $room->delete();
                });
        }
    }

    /**
     * Flatten grouped data (Record<groupId, Item[]>) into a flat array.
     * If already flat (sequential numeric keys with non-array first element), returns as-is.
     *
     * @param  array<int|string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function flattenGroupedData(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        // Check if it's already a flat array (first value is a scalar-keyed assoc array, not a nested array of arrays)
        $firstValue = reset($data);
        if (is_array($firstValue) && ! empty($firstValue) && is_array(reset($firstValue))) {
            // Grouped: Record<groupId, Item[]> — flatten
            $flat = [];
            foreach ($data as $items) {
                foreach ($items as $item) {
                    $flat[] = $item;
                }
            }

            return $flat;
        }

        return array_values($data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    private function ensureFlatList(mixed $list, array $fallback): array
    {
        if (is_array($list) && ! empty($list)) {
            return $this->flattenGroupedData($list);
        }

        return $fallback;
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @param  array<int, array<string, mixed>>  $accommodations
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function ensureRoomLists(mixed $roomLists, array $flightDetails, array $members, array $accommodations): array
    {
        if (is_array($roomLists) && ! empty($roomLists)) {
            return collect($roomLists)
                ->mapWithKeys(function ($rows, $key) {
                    return [
                        (string) $key => $this->flattenGroupedData(is_array($rows) ? $rows : []),
                    ];
                })
                ->toArray();
        }

        $legacyLists = [
            'makkah' => $this->flattenGroupedData(is_array($flightDetails['ui_room_list_makkah'] ?? null) ? $flightDetails['ui_room_list_makkah'] : []),
            'madinah' => $this->flattenGroupedData(is_array($flightDetails['ui_room_list_madinah'] ?? null) ? $flightDetails['ui_room_list_madinah'] : []),
            'others' => $this->flattenGroupedData(is_array($flightDetails['ui_room_list_others'] ?? null) ? $flightDetails['ui_room_list_others'] : []),
        ];

        if (collect($legacyLists)->flatten(1)->isNotEmpty()) {
            return $legacyLists;
        }

        $hotelAccommodations = collect($accommodations)
            ->filter(fn (array $item) => ! empty($item['hotel_name']))
            ->values();

        if ($hotelAccommodations->isEmpty()) {
            return ['makkah' => $members];
        }

        return $hotelAccommodations
            ->mapWithKeys(function (array $accommodation) use ($members) {
                $key = Str::slug((string) ($accommodation['location'] ?? $accommodation['hotel_name'] ?? 'hotel'));

                if ($key === '') {
                    $key = 'hotel';
                }

                return [$key => $members];
            })
            ->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildRoomListsFromRooms(Manifest $manifest, array $members): array
    {
        if ($manifest->rooms->isEmpty()) {
            return [];
        }

        $memberById = collect($members)->keyBy('id');

        return $manifest->rooms
            ->sortBy('sort_order')
            ->groupBy(fn ($room) => (string) ($room->location ?? 'makkah'))
            ->map(function ($rooms) use ($memberById) {
                return $rooms
                    ->values()
                    ->flatMap(function (ManifestRoom $room) use ($memberById) {
                        return $room->roomMembers
                            ->sortBy('sort_order')
                            ->values()
                            ->map(function ($roomMember, int $index) use ($room, $memberById) {
                                $memberRow = $memberById->get($roomMember->manifest_member_id, []);

                                if (! is_array($memberRow)) {
                                    $memberRow = [];
                                }

                                $memberSortOrder = isset($memberRow['sort_order'])
                                    ? (int) $memberRow['sort_order']
                                    : ($index + 1);

                                return array_merge($memberRow, [
                                    'sn' => $memberSortOrder,
                                    'sort_order' => $memberSortOrder,
                                    'sharing_group_key' => 'room-'.$room->id,
                                    'manifest_member_id' => $roomMember->manifest_member_id,
                                    'room_relationship' => $room->group_relationship,
                                    'room_label' => $room->room_label,
                                    'room_number' => $room->room_number,
                                    'sharing_plan' => $memberRow['sharing_plan'] ?? null,
                                    'room_type' => $room->room_type,
                                    'bed_type' => $room->bed_type,
                                    'number_of_beds_checked' => (bool) $room->number_of_beds_checked,
                                    'meal' => $room->meal,
                                    'room_remarks' => $room->remarks,
                                    'remarks' => $memberRow['remarks'] ?? null,
                                ]);
                            });
                    })
                    ->toArray();
            })
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $memberPayload
     */
    private function resolveManifestMemberId(Manifest $manifest, array $memberPayload): ?int
    {
        if (! empty($memberPayload['customer_confirmation_member_id'])) {
            $memberId = $manifest->members()
                ->where('customer_confirmation_member_id', (int) $memberPayload['customer_confirmation_member_id'])
                ->value('id');

            if ($memberId) {
                return (int) $memberId;
            }
        }

        if (! empty($memberPayload['manifest_member_id'])) {
            $memberId = (int) $memberPayload['manifest_member_id'];

            $exists = $manifest->members()
                ->whereKey($memberId)
                ->exists();

            if ($exists) {
                return $memberId;
            }
        }

        if (! empty($memberPayload['package_official_id'])) {
            $memberId = $manifest->members()
                ->where('package_official_id', (int) $memberPayload['package_official_id'])
                ->value('id');

            if ($memberId) {
                return (int) $memberId;
            }
        }

        if (! empty($memberPayload['id'])) {
            $memberId = (int) $memberPayload['id'];

            $exists = $manifest->members()
                ->whereKey($memberId)
                ->exists();

            if ($exists) {
                return $memberId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function syncMemberData(CustomerConfirmationMember $member, array $memberPayload): void
    {
        $memberUpdates = [];

        if (array_key_exists('relationship', $memberPayload) || array_key_exists('role', $memberPayload)) {
            $memberUpdates['relationship'] = $memberPayload['relationship'] ?? $memberPayload['role'] ?? null;
        }

        if (array_key_exists('status', $memberPayload)) {
            $memberUpdates['status'] = $memberPayload['status'] ?: $member->status;
        }

        if ($memberUpdates !== []) {
            $member->update($memberUpdates);
        }
    }

    /**
     * @param  array<string, mixed>  $memberPayload
     */
    private function syncCustomerData(CustomerConfirmationMember $member, array $memberPayload): void
    {
        $customer = $member->customer;

        if (! $customer) {
            return;
        }

        $customerUpdates = [];

        if (array_key_exists('passport_number', $memberPayload) || array_key_exists('passport_no', $memberPayload)) {
            $customerUpdates['passport_number'] = $memberPayload['passport_number'] ?? null;
        }

        if (array_key_exists('nationality', $memberPayload)) {
            $customerUpdates['nationality'] = $memberPayload['nationality'] ?: null;
        }

        if (array_key_exists('gender', $memberPayload)) {
            $customerUpdates['gender'] = $memberPayload['gender'] ?: null;
        }

        if (array_key_exists('issue_place', $memberPayload)) {
            $customerUpdates['passport_place_of_issue'] = $memberPayload['issue_place'] ?: null;
        }

        if (array_key_exists('date_of_issue', $memberPayload)) {
            $customerUpdates['passport_issue_date'] = ! empty($memberPayload['date_of_issue'])
                ? Carbon::parse($memberPayload['date_of_issue'])->format('Y-m-d')
                : null;
        }

        if (array_key_exists('date_of_expiry', $memberPayload)) {
            $customerUpdates['passport_expiry_date'] = ! empty($memberPayload['date_of_expiry'])
                ? Carbon::parse($memberPayload['date_of_expiry'])->format('Y-m-d')
                : null;
        }

        if (array_key_exists('date_of_birth', $memberPayload)) {
            $customerUpdates['date_of_birth'] = ! empty($memberPayload['date_of_birth'])
                ? Carbon::parse($memberPayload['date_of_birth'])->format('Y-m-d')
                : null;
        }

        if (array_key_exists('birth_place', $memberPayload)) {
            $customerUpdates['place_of_birth'] = $memberPayload['birth_place'] ?: null;
        }

        if (array_key_exists('address', $memberPayload)) {
            $customerUpdates['address'] = $memberPayload['address'] ?: null;
        }

        if (array_key_exists('first_time_umrah', $memberPayload) || array_key_exists('is_first_time_umrah', $memberPayload)) {
            $customerUpdates['first_time_umrah'] = $memberPayload['first_time_umrah'] ?? ($memberPayload['is_first_time_umrah'] ?? null);
        }

        if (array_key_exists('has_chronic_disease', $memberPayload)) {
            $customerUpdates['has_chronic_disease'] = $memberPayload['has_chronic_disease'];
        }

        if (array_key_exists('is_using_wheelchair', $memberPayload)) {
            $customerUpdates['is_using_wheelchair'] = $memberPayload['is_using_wheelchair'];
        }

        if (array_key_exists('chronic_disease_details', $memberPayload)) {
            $customerUpdates['chronic_disease_details'] = $memberPayload['chronic_disease_details'] ?: null;
        }

        if (array_key_exists('passport_path', $memberPayload)) {
            $customerUpdates['passport_path'] = $memberPayload['passport_path'] ?: null;
        }

        if (array_key_exists('photo_path', $memberPayload)) {
            $customerUpdates['photo_path'] = $memberPayload['photo_path'] ?: null;
        }

        if ($customerUpdates !== []) {
            $customer->update($customerUpdates);
        }

        $name = trim((string) ($memberPayload['name_as_per_passport'] ?? ''));
        $contactNo = trim((string) ($memberPayload['contact_no'] ?? ''));

        if ($customer->user && ($name !== '' || $contactNo !== '')) {
            $customer->user->update([
                'name' => $name !== '' ? $name : $customer->user->name,
                'contact' => $contactNo !== '' ? $contactNo : $customer->user->contact,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $memberPayload
     * @return array<string, mixed>
     */
    private function buildManifestMemberSnapshot(?CustomerConfirmationMember $member, array $memberPayload, ?PackageOfficial $packageOfficial = null): array
    {
        $customer = $member?->customer;
        $user = $customer?->user;

        return [
            'relationship' => $memberPayload['relationship'] ?? $memberPayload['role'] ?? $member?->relationship,
            'sharing_plan' => $memberPayload['sharing_plan'] ?? $member?->sharing_plan,
            'name' => $memberPayload['name_as_per_passport'] ?? $memberPayload['customer_name'] ?? $packageOfficial?->name ?? $user?->name,
            'arabic_name' => $memberPayload['arabic_name'] ?? null,
            'contact_number' => $memberPayload['contact_no'] ?? $packageOfficial?->contact_number ?? $user?->contact,
            'nationality' => $memberPayload['nationality'] ?? $packageOfficial?->nationality ?? $customer?->nationality,
            'passport_number' => $memberPayload['passport_number'] ?? $packageOfficial?->passport_number ?? $customer?->passport_number,
            'gender' => $memberPayload['gender'] ?? $packageOfficial?->gender ?? $customer?->gender,
            'date_of_birth' => $this->normalizeDateForStorage($memberPayload['date_of_birth'] ?? $packageOfficial?->date_of_birth ?? $customer?->date_of_birth),
            'passport_issue_date' => $this->normalizeDateForStorage($memberPayload['date_of_issue'] ?? $packageOfficial?->passport_issue_date ?? $customer?->passport_issue_date),
            'passport_expiry_date' => $this->normalizeDateForStorage($memberPayload['date_of_expiry'] ?? $packageOfficial?->passport_expiry_date ?? $customer?->passport_expiry_date),
            'passport_place_of_issue' => $memberPayload['issue_place'] ?? $packageOfficial?->passport_place_of_issue ?? $customer?->passport_place_of_issue,
            'place_of_birth' => $memberPayload['birth_place'] ?? $packageOfficial?->place_of_birth ?? $customer?->place_of_birth,
            'address' => $memberPayload['address'] ?? $customer?->address,
            'first_time_umrah' => $memberPayload['first_time_umrah'] ?? $memberPayload['is_first_time_umrah'] ?? $customer?->first_time_umrah,
            'has_chronic_disease' => $memberPayload['has_chronic_disease'] ?? $customer?->has_chronic_disease,
            'is_using_wheelchair' => $memberPayload['is_using_wheelchair'] ?? $customer?->is_using_wheelchair,
            'chronic_disease_details' => $memberPayload['chronic_disease_details'] ?? $customer?->chronic_disease_details,
            'passport_path' => $memberPayload['passport_path'] ?? $customer?->passport_path,
            'photo_path' => $memberPayload['photo_path'] ?? $customer?->photo_path,
        ];
    }

    /**
     * @param  array<string, mixed>  $memberPayload
     */
    private function syncPackageOfficialData(PackageOfficial $packageOfficial, array $memberPayload): void
    {
        $updates = [];

        if (array_key_exists('name_as_per_passport', $memberPayload) || array_key_exists('customer_name', $memberPayload)) {
            $updates['name'] = $memberPayload['name_as_per_passport'] ?? $memberPayload['customer_name'] ?? $packageOfficial->name;
        }

        if (array_key_exists('contact_no', $memberPayload)) {
            $updates['contact_number'] = $memberPayload['contact_no'] ?: null;
        }

        if (array_key_exists('nationality', $memberPayload)) {
            $updates['nationality'] = $memberPayload['nationality'] ?: null;
        }

        if (array_key_exists('passport_number', $memberPayload)) {
            $updates['passport_number'] = $memberPayload['passport_number'] ?: null;
        }

        if (array_key_exists('gender', $memberPayload)) {
            $updates['gender'] = $memberPayload['gender'] ?: null;
        }

        if (array_key_exists('date_of_birth', $memberPayload)) {
            $updates['date_of_birth'] = $this->normalizeDateForStorage($memberPayload['date_of_birth']);
        }

        if (array_key_exists('date_of_issue', $memberPayload)) {
            $updates['passport_issue_date'] = $this->normalizeDateForStorage($memberPayload['date_of_issue']);
        }

        if (array_key_exists('date_of_expiry', $memberPayload)) {
            $updates['passport_expiry_date'] = $this->normalizeDateForStorage($memberPayload['date_of_expiry']);
        }

        if (array_key_exists('issue_place', $memberPayload)) {
            $updates['passport_place_of_issue'] = $memberPayload['issue_place'] ?: null;
        }

        if (array_key_exists('birth_place', $memberPayload)) {
            $updates['place_of_birth'] = $memberPayload['birth_place'] ?: null;
        }

        if ($updates !== []) {
            $packageOfficial->update($updates);
        }
    }

    private function syncPackageStatus(Manifest $manifest, mixed $status): void
    {
        if (! $manifest->package) {
            return;
        }

        if ($status === null) {
            return;
        }

        $nextStatus = $this->normalizePackageStatus($status);

        if ($manifest->package->status === $nextStatus) {
            return;
        }

        $manifest->package->update([
            'status' => $nextStatus,
        ]);
    }

    /**
     * @return array<string, float|string|null>
     */
    private function buildMemberFinancialSnapshot(
        ?CustomerConfirmationMember $member,
        float $packagePrice,
        bool $isOfficial,
    ): array {
        if ($isOfficial || ! $member) {
            return [
                'discount' => null,
                'date_of_deposit_payment' => null,
                'deposit_payment' => null,
                'date_of_second_payment' => null,
                'second_payment' => null,
                'balance_due' => null,
            ];
        }

        $receiptRows = $member->quotationItems
            ->flatMap(function ($quotationItem) {
                return $quotationItem->invoices
                    ->flatMap(function ($invoice) {
                        $memberCount = $invoice->quotationItems
                            ->pluck('customer_confirmation_member_id')
                            ->filter()
                            ->unique()
                            ->count();

                        $divisor = max($memberCount, 1);

                        return $invoice->receipt
                            ->map(function ($receipt) use ($divisor) {
                                return [
                                    'receipt' => $receipt,
                                    'share_amount' => (float) ($receipt->amount ?? 0) / $divisor,
                                ];
                            });
                    });
            })
            ->filter(function (array $row) {
                return isset($row['receipt']) && $row['receipt'] !== null;
            })
            ->unique(function (array $row): int {
                return (int) $row['receipt']->id;
            })
            ->sort(function ($left, $right) {
                $leftDate = $left['receipt']->receipt_date;
                $rightDate = $right['receipt']->receipt_date;

                if ($leftDate && $rightDate) {
                    $comparison = $leftDate->getTimestamp() <=> $rightDate->getTimestamp();

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return (int) $left['receipt']->id <=> (int) $right['receipt']->id;
            })
            ->values();

        $receipts = $receiptRows
            ->pluck('receipt')
            ->values();

        $receiptShareById = $receiptRows
            ->mapWithKeys(function (array $row): array {
                return [
                    (int) $row['receipt']->id => (float) ($row['share_amount'] ?? 0),
                ];
            });

        $firstReceipt = $receipts->get(0);
        $followUpReceipts = $receipts->slice(1)->values();
        $latestFollowUpReceipt = $followUpReceipts->last();

        $paidAmountFromReceipts = (float) $receiptShareById->sum();

        $followUpAmount = (float) $followUpReceipts->sum(function ($receipt) use ($receiptShareById): float {
            return (float) ($receiptShareById[(int) $receipt->id] ?? 0);
        });

        $allocations = $member->receiptAllocations
            ->filter(function ($allocation) {
                return $allocation->receipt !== null;
            })
            ->sort(function ($left, $right) {
                $leftDate = $left->receipt?->receipt_date;
                $rightDate = $right->receipt?->receipt_date;

                if ($leftDate && $rightDate) {
                    $comparison = $leftDate->getTimestamp() <=> $rightDate->getTimestamp();

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->values();

        $paidAmountFromAllocations = (float) $allocations->sum(function ($allocation): float {
            return (float) ($allocation->allocated_amount ?? 0);
        });

        $paidAmount = $paidAmountFromReceipts > 0
            ? $paidAmountFromReceipts
            : $paidAmountFromAllocations;

        $quotationIds = $member->quotationItems
            ->pluck('quotation_id')
            ->filter()
            ->unique()
            ->values();

        $discount = 0.0;

        foreach ($quotationIds as $quotationId) {
            $quotation = $member->quotationItems
                ->firstWhere('quotation_id', $quotationId)
                ?->quotation;

            if (! $quotation) {
                continue;
            }

            $discountTotal = (float) $quotation->quotationExtensions
                ->where('type', 'discount')
                ->sum(function ($extension): float {
                    return (float) ($extension->amount ?? 0);
                });

            $discountTotal = abs($discountTotal);

            if ($discountTotal <= 0) {
                continue;
            }

            $quotedMemberCount = $quotation->quotationItems
                ->pluck('customer_confirmation_member_id')
                ->filter()
                ->unique()
                ->count();

            $divisor = max($quotedMemberCount, 1);
            $discount += $discountTotal / $divisor;
        }

        $depositPayment = $firstReceipt
            ? round((float) ($receiptShareById[(int) $firstReceipt->id] ?? 0), 2)
            : null;

        $secondPayment = $latestFollowUpReceipt
            ? round($followUpAmount, 2)
            : null;

        $balanceDue = round(max(
            $packagePrice
                - $discount
                - (float) ($depositPayment ?? 0)
                - (float) ($secondPayment ?? 0),
            0
        ), 2);

        return [
            'discount' => round($discount, 2),
            'date_of_deposit_payment' => $this->formatDateForUi($firstReceipt?->receipt_date),
            'deposit_payment' => $depositPayment,
            'date_of_second_payment' => $this->formatDateForUi($latestFollowUpReceipt?->receipt_date),
            'second_payment' => $secondPayment,
            'balance_due' => $balanceDue,
        ];
    }

    /**
     * @param  array<string, mixed>  $documents
     */
    private function syncManifestDocuments(Manifest $manifest, array $documents): void
    {
        $allowedFields = ['train_tickets', 'flight_tickets', 'visa', 'hotel', 'passport', 'photo'];
        $existingFiles = $manifest->files()->get()->groupBy('field');
        $rowsToPersist = [];

        foreach ($allowedFields as $field) {
            $entries = $documents[$field] ?? [];

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $isRemoved = (bool) ($entry['removed'] ?? false);
                if ($isRemoved) {
                    continue;
                }

                $uploadedPath = $this->storeDocumentFile($entry['file'] ?? null, $field);
                $requestedName = $this->normalizeNullableString($entry['file_name'] ?? null);
                $defaultFileName = $this->buildDefaultDocumentName($entry['file'] ?? null, $field);
                $existingPath = $this->normalizeNullableString($entry['file_path'] ?? null);
                $filePath = $uploadedPath ?? $existingPath;

                if (! $filePath) {
                    continue;
                }

                $rowsToPersist[] = [
                    'field' => $field,
                    'file_name' => $requestedName ?? $defaultFileName ?? pathinfo(basename($filePath), PATHINFO_FILENAME),
                    'file_path' => $filePath,
                ];
            }
        }

        $preservedPaths = collect($rowsToPersist)
            ->pluck('file_path')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->all();

        $existingManagedFiles = collect($allowedFields)
            ->flatMap(fn (string $field) => $existingFiles->get($field, collect()))
            ->values();

        foreach ($existingManagedFiles as $existingFile) {
            if (! in_array($existingFile->file_path, $preservedPaths, true) && $existingFile->file_path) {
                Storage::disk('public')->delete($existingFile->file_path);
            }
        }

        $manifest->files()->whereIn('field', $allowedFields)->delete();

        foreach ($rowsToPersist as $row) {
            $manifest->files()->create($row);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $receiptDocuments
     */
    private function persistMemberReceiptDocuments(ManifestMember $manifestMember, array $receiptDocuments): void
    {
        $rowsToPersist = [];

        foreach ($receiptDocuments as $receiptDocument) {
            if (! is_array($receiptDocument)) {
                continue;
            }

            $isRemoved = (bool) ($receiptDocument['removed'] ?? false);
            if ($isRemoved) {
                continue;
            }

            $uploadedPath = $this->storeDocumentFile($receiptDocument['file'] ?? null, 'receipt');
            $requestedName = $this->normalizeNullableString($receiptDocument['file_name'] ?? null);
            $defaultFileName = $this->buildDefaultDocumentName($receiptDocument['file'] ?? null, 'receipt');
            $existingPath = $this->normalizeNullableString($receiptDocument['file_path'] ?? null);
            $filePath = $uploadedPath ?? $existingPath;

            if (! $filePath) {
                continue;
            }

            $rowsToPersist[] = [
                'field' => 'receipt',
                'file_name' => $requestedName ?? $defaultFileName ?? pathinfo(basename($filePath), PATHINFO_FILENAME),
                'file_path' => $filePath,
            ];
        }

        $existingReceiptFiles = $manifestMember->files()->where('field', 'receipt')->get();
        $preservedPaths = collect($rowsToPersist)
            ->pluck('file_path')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->all();

        foreach ($existingReceiptFiles as $existingReceiptFile) {
            if (! in_array($existingReceiptFile->file_path, $preservedPaths, true) && $existingReceiptFile->file_path) {
                Storage::disk('public')->delete($existingReceiptFile->file_path);
            }
        }

        $manifestMember->files()->where('field', 'receipt')->delete();

        foreach ($rowsToPersist as $row) {
            $manifestMember->files()->create($row);
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildManifestDocumentPayload(Manifest $manifest): array
    {
        $allowedFields = ['train_tickets', 'flight_tickets', 'visa', 'hotel', 'passport', 'photo'];
        $grouped = $manifest->files->groupBy('field');
        $documents = [];

        foreach ($allowedFields as $field) {
            $documents[$field] = ($grouped->get($field) ?? collect())
                ->map(function (ModelFile $file): array {
                    return [
                        'id' => $file->id,
                        'file' => null,
                        'file_name' => $file->file_name,
                        'file_path' => $file->file_path,
                        'removed' => false,
                    ];
                })
                ->values()
                ->all();
        }

        return $documents;
    }

    private function storeDocumentFile(mixed $file, string $field): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store("manifests/{$field}", 'public');
    }

    private function buildDefaultDocumentName(mixed $file, string $field): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $normalized = $this->normalizeNullableString($originalName);

        return $normalized ?? $field;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePackageStatus(mixed $status): string
    {
        $normalized = strtolower(trim((string) $status));

        if ($normalized === 'closed') {
            return 'closed';
        }

        return 'open';
    }

    private function normalizeDateForStorage(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->format('Y-m-d');
    }

    private function formatDateForUi(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->translatedFormat('d F Y');
    }

    private function getPackagePriceForSharingPlan(?\App\Models\Package $package, ?string $sharingPlan): float
    {
        if (! $package) {
            return 0.0;
        }

        return match (strtolower((string) $sharingPlan)) {
            'single' => (float) ($package->price_single ?? 0),
            'double' => (float) ($package->price_double ?? 0),
            'triple' => (float) ($package->price_triple ?? 0),
            'quad' => (float) ($package->price_quad ?? 0),
            default => 0.0,
        };
    }
}
