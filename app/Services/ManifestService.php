<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestPayment;
use App\Models\ManifestRoom;
use App\Models\ManifestSharingGroup;
use App\Models\ModelFile;
use App\Models\PackageOfficial;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $data = Manifest::with('package')
            ->withCount([
                'travelers as members_count' => function ($query) {
                    $query->whereNull('package_official_id');
                },
            ])
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
            $manifest = Manifest::create([
                'package_id' => $data['package_id'],
                'in_charge_official_id' => $data['in_charge_official_id'] ?? null,
                'manifest_number' => NumberGenerator::generate('manifest'),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncPackageStatus($manifest, $data['status'] ?? null);

            $this->syncTravelers($manifest, $data['travelers'] ?? []);
            $this->syncManifestDocuments($manifest, $data['documents'] ?? []);
            $this->syncTravelerReceiptDocuments($manifest, $data['travelers'] ?? []);
            $this->syncRooms($manifest, $data['rooms'] ?? []);
            $this->syncPayments($manifest, $data['payments'] ?? []);

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
            'travelers.sharingGroup',
            'travelers.packageOfficial',
            'travelers.collectionItem',
            'travelers.files',
            'travelers.confirmationMember.confirmation.enquiry',
            'travelers.confirmationMember.customer.user',
            'travelers.confirmationMember.receiptAllocations.receipt',
            'travelers.confirmationMember.quotationItems.quotation.quotationExtensions',
            'travelers.confirmationMember.quotationItems.quotation.quotationItems',
            'travelers.confirmationMember.quotationItems.invoices.quotationItems',
            'travelers.confirmationMember.quotationItems.invoices.receipt',
            'rooms.roomMembers.traveler',
            'payments.traveler',
            'files',
            'manifestSharingGroups.customerConfirmation',
            'manifestSharingGroups.members.confirmationMember.confirmation.enquiry',
            'manifestSharingGroups.members.confirmationMember.customer.user',
        ])->findOrFail($id);

        $travelers = $manifest->travelers
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
            ->map(function ($traveler, $index) use ($manifest) {
                $member = $traveler->confirmationMember;
                $confirmation = $member?->confirmation;
                $enquiryCreatedAt = $confirmation?->enquiry?->created_at;
                $customer = $member?->customer;
                $user = $customer?->user;
                $packageOfficial = $traveler->packageOfficial;
                $sharingPlan = $traveler->sharing_plan ?? $member?->sharing_plan;
                $collectionItem = $traveler->collectionItem;
                $packagePrice = $this->getPackagePriceForSharingPlan($manifest->package, $sharingPlan);
                $financialSnapshot = $this->buildTravelerFinancialSnapshot(
                    $member,
                    $packagePrice,
                    $traveler->package_official_id !== null,
                );

                return [
                    'id' => $traveler->id,
                    'sn' => $index + 1,
                    'customer_id' => $customer?->id,
                    'customer_confirmation_member_id' => $traveler->customer_confirmation_member_id,
                    'package_official_id' => $traveler->package_official_id,
                    'customer_confirmation_id' => $member?->customer_confirmation_id,
                    'customer_confirmation_number' => $confirmation?->number,
                    'is_official' => $traveler->package_official_id !== null,
                    'customer_name' => $traveler->name ?? $packageOfficial?->name ?? $user?->name,
                    'name_as_per_passport' => $traveler->name ?? $packageOfficial?->name ?? $user?->name,
                    'arabic_name' => $traveler->arabic_name,
                    'contact_no' => $traveler->contact_number ?? $packageOfficial?->contact_number ?? $user?->contact,
                    'role' => $traveler->role ?? $member?->role,
                    'relationship' => $traveler->sharingGroup?->relation,
                    'group_remarks' => $traveler->sharingGroup?->remarks,
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
                    'manifest_sharing_group_id' => $traveler->manifest_sharing_group_id,
                    'sharing_group_id' => $traveler->manifest_sharing_group_id,
                    'sharing_group_key' => $traveler->manifest_sharing_group_id
                        ? 'group-'.$traveler->manifest_sharing_group_id
                        : 'group-'.$traveler->id,
                    'group_sort_order' => $traveler->sharingGroup?->sort_order,
                    'sort_order' => $traveler->sort_order,
                    'package_category' => $confirmation?->package_category,
                    'date_of_sign_up' => ($enquiryCreatedAt ?? $confirmation?->created_at)?->translatedFormat('d F Y'),
                    'package_price' => $packagePrice,
                    'discount' => $financialSnapshot['discount'],
                    'date_of_deposit_payment' => $financialSnapshot['date_of_deposit_payment'],
                    'deposit_payment' => $financialSnapshot['deposit_payment'],
                    'date_of_second_payment' => $financialSnapshot['date_of_second_payment'],
                    'second_payment' => $financialSnapshot['second_payment'],
                    'balance_due' => $financialSnapshot['balance_due'],
                    'is_first_time_umrah' => $traveler->first_time_umrah ?? $customer?->first_time_umrah,
                    'passport_number' => $traveler->passport_number ?? $packageOfficial?->passport_number ?? $customer?->passport_number,
                    'nationality' => $traveler->nationality ?? $packageOfficial?->nationality ?? $customer?->nationality,
                    'gender' => $traveler->gender ?? $packageOfficial?->gender ?? $customer?->gender,
                    'date_of_issue' => $this->formatDateForUi($traveler->passport_issue_date ?? $packageOfficial?->passport_issue_date ?? $customer?->passport_issue_date),
                    'date_of_expiry' => $this->formatDateForUi($traveler->passport_expiry_date ?? $packageOfficial?->passport_expiry_date ?? $customer?->passport_expiry_date),
                    'issue_place' => $traveler->passport_place_of_issue ?? $packageOfficial?->passport_place_of_issue ?? $customer?->passport_place_of_issue,
                    'birth_place' => $traveler->place_of_birth ?? $packageOfficial?->place_of_birth ?? $customer?->place_of_birth,
                    'date_of_birth' => $this->formatDateForUi($traveler->date_of_birth ?? $packageOfficial?->date_of_birth ?? $customer?->date_of_birth),
                    'age' => ($traveler->date_of_birth ?? $packageOfficial?->date_of_birth ?? $customer?->date_of_birth)?->age,
                    'address' => $traveler->address ?? $customer?->address,
                    'first_time_umrah' => $traveler->first_time_umrah ?? $customer?->first_time_umrah,
                    'has_chronic_disease' => $traveler->has_chronic_disease ?? $customer?->has_chronic_disease,
                    'chronic_disease_details' => $traveler->chronic_disease_details ?? $customer?->chronic_disease_details,
                    'passport_path' => $traveler->passport_path ?? $customer?->passport_path,
                    'photo_path' => $traveler->photo_path ?? $customer?->photo_path,
                    'remarks' => $traveler->remarks,
                    'receipt_documents' => $traveler->files
                        ->where('field', 'receipt')
                        ->map(fn (ModelFile $file) => [
                            'id' => $file->id,
                            'file_name' => $file->file_name,
                            'file_path' => $file->file_path,
                        ])
                        ->values()
                        ->toArray(),
                    'status' => $member?->status ?? 'draft',
                ];
            })
            ->toArray();

        $documents = $this->buildManifestDocumentPayload($manifest);

        $roomLists = $this->buildRoomListsFromRooms($manifest, $travelers);

        if ($roomLists === []) {
            $roomLists = $this->ensureRoomLists(
                null,
                [],
                $travelers,
                $manifest->package?->accommodations?->toArray() ?? [],
            );
        }
        $airlineList = $this->ensureFlatList(null, $travelers);

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
            'travelers' => $travelers,
            'roomLists' => $roomLists,
            'airlineList' => $airlineList,
            'documents' => $documents,
            'rooms' => $manifest->rooms->map(function ($r) {
                return [
                    'id' => $r->id,
                    'sort_order' => $r->sort_order,
                    'location' => $r->location,
                    'relationship' => $r->relationship,
                    'room_label' => $r->room_label,
                    'room_number' => $r->room_number,
                    'room_type' => $r->room_type,
                    'bed_type' => $r->bed_type,
                    'capacity' => $r->capacity,
                    'sharing_plan' => $r->sharing_plan,
                    'status' => $r->status,
                    'meal' => $r->meal,
                    'number_of_beds_checked' => (bool) $r->number_of_beds_checked,
                    'remarks' => $r->remarks,
                    'members' => $r->roomMembers->map(function ($rm) {
                        $member = $rm->traveler?->confirmationMember;
                        $customer = $member?->customer;
                        $official = $rm->traveler?->packageOfficial;

                        return [
                            'id' => $rm->id,
                            'manifest_traveler_id' => $rm->manifest_traveler_id,
                            'traveler_name' => $rm->traveler?->name ?? $official?->name ?? $customer?->user?->name,
                            'role_in_room' => $member?->role,
                            'sort_order' => $rm->sort_order,
                            'remarks' => $rm->remarks,
                            'customer_confirmation_member_id' => $member?->id,
                            'customer_id' => $customer?->id,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
            'payments' => $manifest->payments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'manifest_traveler_id' => $p->manifest_traveler_id,
                    'traveler_name' => $p->traveler_name,
                    'linked_traveler_name' => $p->traveler?->confirmationMember?->customer?->user?->name,
                    'description' => $p->description,
                    'amount' => $p->amount,
                    'paid_amount' => $p->paid_amount,
                    'outstanding_amount' => $p->outstanding_amount,
                    'payment_date' => $p->payment_date_formatted,
                    'status' => $p->status,
                ];
            })->toArray(),
            'sharing_groups' => $manifest->manifestSharingGroups->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'customer_confirmation_id' => $msg->customer_confirmation_id,
                    'sort_order' => $msg->sort_order,
                    'relation' => $msg->relation,
                    'remarks' => $msg->remarks,
                    'members' => $msg->members->map(function ($m) {
                        $cm = $m->confirmationMember;

                        return [
                            'id' => $m->id,
                            'customer_confirmation_member_id' => $m->customer_confirmation_member_id,
                            'role_in_group' => $cm?->role,
                            'sort_order' => $m->sort_order,
                            'customer_name' => $cm?->customer?->user?->name,
                            'customer_id' => $cm?->customer_id,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }

    public function update(array $data, int $id): Manifest
    {
        return DB::transaction(function () use ($data, $id) {
            $manifest = Manifest::findOrFail($id);

            $manifest->update([
                'package_id' => $data['package_id'] ?? $manifest->package_id,
                'in_charge_official_id' => $data['in_charge_official_id'] ?? null,
                'notes' => $data['notes'] ?? $manifest->notes,
            ]);

            $this->syncPackageStatus($manifest, $data['status'] ?? null);

            if (isset($data['travelers'])) {
                $this->syncTravelers($manifest, $data['travelers']);
                $this->syncTravelerReceiptDocuments($manifest, $data['travelers']);
            }

            if (isset($data['documents'])) {
                $this->syncManifestDocuments($manifest, $data['documents']);
            }

            if (isset($data['rooms'])) {
                $this->syncRooms($manifest, $data['rooms']);
            }

            if (isset($data['payments'])) {
                $this->syncPayments($manifest, $data['payments']);
            }

            $manifest = $manifest->fresh();

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Manifest updated successfully #'.$manifest->id);

            return $manifest;
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
                'relationship' => $data['relationship'] ?? null,
                'room_label' => $data['room_label'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'bed_type' => $data['bed_type'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'sharing_plan' => $data['sharing_plan'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'meal' => $data['meal'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            if (! empty($data['members'])) {
                foreach ($data['members'] as $index => $member) {
                    $room->roomMembers()->create([
                        'manifest_traveler_id' => $member['manifest_traveler_id'],
                        'sort_order' => (int) ($member['sort_order'] ?? ($index + 1)),
                        'remarks' => $member['remarks'] ?? null,
                    ]);
                }
            }

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Room added to manifest #'.$manifest->id);

            return $room->load('roomMembers.traveler');
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
                'relationship' => $data['relationship'] ?? $room->relationship,
                'room_label' => $data['room_label'] ?? $room->room_label,
                'room_number' => $data['room_number'] ?? $room->room_number,
                'room_type' => $data['room_type'] ?? $room->room_type,
                'bed_type' => $data['bed_type'] ?? $room->bed_type,
                'capacity' => $data['capacity'] ?? $room->capacity,
                'sharing_plan' => $data['sharing_plan'] ?? $room->sharing_plan,
                'status' => $data['status'] ?? $room->status,
                'meal' => $data['meal'] ?? $room->meal,
                'remarks' => $data['remarks'] ?? $room->remarks,
            ]);

            if (isset($data['members'])) {
                $room->roomMembers()->delete();
                foreach ($data['members'] as $index => $member) {
                    $room->roomMembers()->create([
                        'manifest_traveler_id' => $member['manifest_traveler_id'],
                        'sort_order' => (int) ($member['sort_order'] ?? ($index + 1)),
                        'remarks' => $member['remarks'] ?? null,
                    ]);
                }
            }

            activity()
                ->performedOn($room->manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $room->manifest_id])
                ->log('Room updated in manifest #'.$room->manifest_id);

            return $room->fresh()->load('roomMembers.traveler');
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
     * Add a single payment to a manifest.
     */
    public function addPayment(int $manifestId, array $data): ManifestPayment
    {
        return DB::transaction(function () use ($manifestId, $data) {
            $manifest = Manifest::findOrFail($manifestId);

            $payment = $manifest->payments()->create([
                'manifest_traveler_id' => $data['manifest_traveler_id'] ?? null,
                'traveler_name' => $data['traveler_name'] ?? null,
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'] ?? 0,
                'paid_amount' => $data['paid_amount'] ?? 0,
                'outstanding_amount' => $data['outstanding_amount'] ?? 0,
                'payment_date' => ! empty($data['payment_date']) ? Carbon::parse($data['payment_date'])->format('Y-m-d') : null,
                'status' => $data['status'] ?? 'pending',
            ]);

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Payment added to manifest #'.$manifest->id);

            return $payment->load('traveler');
        });
    }

    /**
     * Update a payment.
     */
    public function updatePayment(int $paymentId, array $data): ManifestPayment
    {
        return DB::transaction(function () use ($paymentId, $data) {
            $payment = ManifestPayment::findOrFail($paymentId);

            $payment->update([
                'manifest_traveler_id' => $data['manifest_traveler_id'] ?? $payment->manifest_traveler_id,
                'traveler_name' => $data['traveler_name'] ?? $payment->traveler_name,
                'description' => $data['description'] ?? $payment->description,
                'amount' => $data['amount'] ?? $payment->amount,
                'paid_amount' => $data['paid_amount'] ?? $payment->paid_amount,
                'outstanding_amount' => $data['outstanding_amount'] ?? $payment->outstanding_amount,
                'payment_date' => ! empty($data['payment_date']) ? Carbon::parse($data['payment_date'])->format('Y-m-d') : $payment->payment_date,
                'status' => $data['status'] ?? $payment->status,
            ]);

            activity()
                ->performedOn($payment->manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $payment->manifest_id])
                ->log('Payment updated in manifest #'.$payment->manifest_id);

            return $payment->fresh()->load('traveler');
        });
    }

    /**
     * Delete a payment.
     */
    public function deletePayment(int $paymentId): bool
    {
        $payment = ManifestPayment::find($paymentId);
        if (! $payment) {
            return false;
        }

        $manifestId = $payment->manifest_id;
        $payment->delete();

        activity()
            ->performedOn(Manifest::find($manifestId))
            ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifestId])
            ->log('Payment deleted from manifest #'.$manifestId);

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
            'relation' => null,
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
     * Includes passport, date-of-birth, and other traveler-relevant data.
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
                            'role' => $member->role,
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
     * Sync travelers for a manifest (delete-and-recreate strategy).
     *
     * Accepts either a flat array of travelers or a grouped Record<groupId, TravelerSchema[]> from the frontend.
     */
    private function syncTravelers(Manifest $manifest, array $travelers): void
    {
        $previousTravelerIds = $manifest->travelers()->pluck('id');

        if ($previousTravelerIds->isNotEmpty()) {
            ModelFile::query()
                ->where('fileable_type', ManifestMember::class)
                ->whereIn('fileable_id', $previousTravelerIds->all())
                ->where('field', 'receipt')
                ->delete();
        }

        $manifest->manifestSharingGroups()->delete();
        $manifest->travelers()->delete();

        $flatTravelers = collect($this->flattenGroupedData($travelers))
            ->values()
            ->map(function (array $traveler, int $index): array {
                $traveler['_original_index'] = $index;

                return $traveler;
            })
            ->values()
            ->all();

        $confirmationMemberIds = collect($flatTravelers)
            ->pluck('customer_confirmation_member_id')
            ->filter(fn ($value) => ! empty($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $confirmationIdMap = $confirmationMemberIds->isEmpty()
            ? collect()
            : CustomerConfirmationMember::query()
                ->whereIn('id', $confirmationMemberIds->all())
                ->pluck('customer_confirmation_id', 'id')
                ->map(fn ($value) => (int) $value);

        $groupedTravelers = [];
        $groupSizes = [];
        $groupBuckets = [];
        $groupKeyCounter = [];
        $groupTypeByKey = [];

        foreach ($flatTravelers as $index => $traveler) {
            $groupKey = isset($traveler['sharing_group_key']) && is_string($traveler['sharing_group_key'])
                ? trim($traveler['sharing_group_key'])
                : '';

            $confirmationMemberId = ! empty($traveler['customer_confirmation_member_id'])
                ? (int) $traveler['customer_confirmation_member_id']
                : null;

            $confirmationId = ! empty($traveler['customer_confirmation_id'])
                ? (int) $traveler['customer_confirmation_id']
                : ($confirmationMemberId ? (int) ($confirmationIdMap->get($confirmationMemberId) ?? 0) : 0);

            $sharingPlan = isset($traveler['sharing_plan']) && is_string($traveler['sharing_plan'])
                ? strtolower(trim($traveler['sharing_plan']))
                : '';

            $travelerType = ! empty($traveler['package_official_id']) ? 'official' : 'member';

            $capacity = $this->capacityFromSharingPlan($sharingPlan !== '' ? $sharingPlan : null);
            $bucketKey = $confirmationId > 0 && $sharingPlan !== ''
                ? $confirmationId.'|'.$sharingPlan.'|'.$travelerType
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
                $groupId = $traveler['manifest_sharing_group_id']
                    ?? $traveler['sharing_group_id']
                    ?? null;

                if (! empty($groupId)) {
                    $groupKey = 'group-'.((int) $groupId);
                } else {
                    $groupKey = 'solo-'.((int) ($traveler['customer_confirmation_member_id'] ?? $traveler['customer_id'] ?? ($index + 1)));
                }
            }

            if (isset($groupTypeByKey[$groupKey]) && $groupTypeByKey[$groupKey] !== $travelerType) {
                $groupKey = $groupKey.'|'.$travelerType;
            }

            $groupTypeByKey[$groupKey] = $travelerType;

            if ($bucketKey !== null && ! in_array($groupKey, $groupBuckets[$bucketKey] ?? [], true)) {
                $groupBuckets[$bucketKey][] = $groupKey;
            }

            $groupSizes[$groupKey] = ($groupSizes[$groupKey] ?? 0) + 1;

            $traveler['sharing_group_key'] = $groupKey;

            $groupedTravelers[$groupKey][] = $traveler;
        }

        $nonOfficialGroups = [];
        $officialGroups = [];

        foreach ($groupedTravelers as $groupKey => $groupTravelers) {
            // Preserve the exact incoming sequence from main tab for member order inside each group.
            $sortedGroupTravelers = array_values($groupTravelers);

            $isOfficialGroup = collect($sortedGroupTravelers)
                ->every(fn (array $traveler) => ! empty($traveler['package_official_id']));

            $groupPayload = [
                'key' => $groupKey,
                'travelers' => $sortedGroupTravelers,
                'group_sort_order' => (int) ($sortedGroupTravelers[0]['group_sort_order'] ?? PHP_INT_MAX),
                'original_index' => (int) ($sortedGroupTravelers[0]['_original_index'] ?? PHP_INT_MAX),
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

        foreach ($orderedGroups as $groupPayload) {
            $groupTravelers = $groupPayload['travelers'];
            $firstTraveler = $groupTravelers[0] ?? [];

            $groupCustomerConfirmationId = null;

            if (! empty($firstTraveler['customer_confirmation_id'])) {
                $groupCustomerConfirmationId = (int) $firstTraveler['customer_confirmation_id'];
            } elseif (! empty($firstTraveler['customer_confirmation_member_id'])) {
                $groupCustomerConfirmationId = CustomerConfirmationMember::query()
                    ->whereKey((int) $firstTraveler['customer_confirmation_member_id'])
                    ->value('customer_confirmation_id');
            }

            $manifestSharingGroup = $manifest->manifestSharingGroups()->create([
                'customer_confirmation_id' => $groupCustomerConfirmationId,
                'sort_order' => $groupSortOrder,
                'relation' => $firstTraveler['relationship'] ?? null,
                'remarks' => $firstTraveler['group_remarks'] ?? null,
            ]);

            foreach (array_values($groupTravelers) as $memberSortOrder => $traveler) {
                $memberId = isset($traveler['customer_confirmation_member_id'])
                    ? (int) $traveler['customer_confirmation_member_id']
                    : null;

                $member = $memberId
                    ? CustomerConfirmationMember::query()->with(['customer.user'])->find($memberId)
                    : null;

                if ($member) {
                    $this->syncMemberData($member, $traveler);
                    $this->syncCustomerData($member, $traveler);
                }

                $packageOfficial = null;
                if (! empty($traveler['package_official_id'])) {
                    $packageOfficial = PackageOfficial::query()->find((int) $traveler['package_official_id']);

                    if ($packageOfficial) {
                        $this->syncPackageOfficialData($packageOfficial, $traveler);
                    }
                }

                $createdTraveler = $manifest->travelers()->create([
                    'manifest_sharing_group_id' => $manifestSharingGroup->id,
                    'customer_confirmation_member_id' => $member?->id,
                    'package_official_id' => $packageOfficial?->id,
                    ...$this->buildManifestMemberSnapshot($member, $traveler, $packageOfficial),
                    'sort_order' => $memberSortOrder + 1,
                    'remarks' => $traveler['remarks'] ?? null,
                ]);

                $createdTraveler->collectionItem()->updateOrCreate(
                    [],
                    [
                        'course_1' => (bool) ($traveler['course_1'] ?? false),
                        'course_2' => (bool) ($traveler['course_2'] ?? false),
                        'lanyard' => (bool) ($traveler['lanyard'] ?? false),
                        'luggage_tag' => (bool) ($traveler['luggage_tag'] ?? false),
                        'cabin_tag' => (bool) ($traveler['cabin_tag'] ?? false),
                        'passport_cover' => (bool) ($traveler['passport_cover'] ?? false),
                        'umrah_guidebook' => (bool) ($traveler['umrah_guidebook'] ?? false),
                        'sling_bag' => (bool) ($traveler['sling_bag'] ?? false),
                        'cabin_size_luggage' => (bool) ($traveler['cabin_size_luggage'] ?? false),
                        'umrah_essentials' => (bool) ($traveler['umrah_essentials'] ?? false),
                    ],
                );
            }

            $groupSortOrder++;
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
     * Sync rooms for a manifest (delete-and-recreate strategy).
     */
    private function syncRooms(Manifest $manifest, array $rooms): void
    {
        // Delete existing room members first, then rooms
        foreach ($manifest->rooms as $room) {
            $room->roomMembers()->delete();
        }
        $manifest->rooms()->delete();

        $flatRooms = $this->flattenGroupedData($rooms);

        $roomPayloads = [];

        foreach ($flatRooms as $roomIndex => $room) {
            $sharingPlan = isset($room['sharing_plan']) && is_string($room['sharing_plan'])
                ? strtolower(trim($room['sharing_plan']))
                : null;

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
                $manifestTravelerId = $this->resolveManifestTravelerId($manifest, is_array($member) ? $member : []);

                if (! $manifestTravelerId) {
                    continue;
                }

                $resolvedMembers[] = [
                    ...(is_array($member) ? $member : []),
                    'manifest_traveler_id' => $manifestTravelerId,
                ];
            }

            $roomPayloads[] = [
                'base' => $room,
                'members' => collect($resolvedMembers)
                    ->values()
                    ->sortBy(fn (array $member) => (int) ($member['sort_order'] ?? PHP_INT_MAX))
                    ->values()
                    ->all(),
                'sharing_plan' => $sharingPlan,
                'room_type' => $roomType,
                'bed_type' => $bedType,
                'original_index' => $roomIndex,
            ];
        }

        $roomSortOrder = 1;

        $orderedRoomPayloads = collect($roomPayloads)
            ->sortBy(fn (array $payload) => (int) ($payload['original_index'] ?? PHP_INT_MAX))
            ->values()
            ->all();

        foreach ($orderedRoomPayloads as $payload) {
            $baseRoom = $payload['base'];
            $roomMembers = $payload['members'];

            $createdRoom = $manifest->rooms()->create([
                'sort_order' => $roomSortOrder,
                'location' => $baseRoom['location'] ?? null,
                'relationship' => $baseRoom['relationship'] ?? null,
                'room_label' => $baseRoom['room_label'] ?? null,
                'room_number' => $baseRoom['room_number'] ?? $baseRoom['room_no'] ?? null,
                'room_type' => $payload['room_type'],
                'bed_type' => $payload['bed_type'],
                'capacity' => $baseRoom['capacity'] ?? ($roomMembers === [] ? null : count($roomMembers)),
                'sharing_plan' => $payload['sharing_plan'],
                'status' => $baseRoom['status'] ?? 'pending',
                'meal' => $baseRoom['meal'] ?? null,
                'number_of_beds_checked' => (bool) ($baseRoom['number_of_beds_checked'] ?? false),
                'remarks' => $baseRoom['remarks'] ?? null,
            ]);

            foreach ($roomMembers as $index => $member) {
                $manifestTravelerId = ! empty($member['manifest_traveler_id'])
                    ? (int) $member['manifest_traveler_id']
                    : $this->resolveManifestTravelerId($manifest, $member);

                if (! $manifestTravelerId) {
                    continue;
                }

                if (! empty($member['customer_confirmation_member_id']) && array_key_exists('sharing_plan', $member)) {
                    $confirmationMember = CustomerConfirmationMember::query()->find((int) $member['customer_confirmation_member_id']);

                    if ($confirmationMember) {
                        $this->syncMemberData($confirmationMember, [
                            'sharing_plan' => $member['sharing_plan'],
                        ]);
                    }
                }

                $createdRoom->roomMembers()->create([
                    'manifest_traveler_id' => $manifestTravelerId,
                    'sort_order' => (int) ($member['sort_order'] ?? ($index + 1)),
                    'remarks' => $member['remarks'] ?? null,
                ]);
            }

            $roomSortOrder++;
        }
    }

    /**
     * Sync payments for a manifest (delete-and-recreate strategy).
     */
    private function syncPayments(Manifest $manifest, array $payments): void
    {
        $manifest->payments()->delete();

        foreach ($payments as $payment) {
            $manifest->payments()->create([
                'manifest_traveler_id' => $payment['manifest_traveler_id'] ?? null,
                'traveler_name' => $payment['traveler_name'] ?? null,
                'description' => $payment['description'] ?? null,
                'amount' => $payment['amount'] ?? 0,
                'paid_amount' => $payment['paid_amount'] ?? 0,
                'outstanding_amount' => $payment['outstanding_amount'] ?? 0,
                'payment_date' => ! empty($payment['payment_date']) ? Carbon::parse($payment['payment_date'])->format('Y-m-d') : null,
                'status' => $payment['status'] ?? 'pending',
            ]);
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
     * @param  array<int, array<string, mixed>>  $travelers
     * @param  array<int, array<string, mixed>>  $accommodations
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function ensureRoomLists(mixed $roomLists, array $flightDetails, array $travelers, array $accommodations): array
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
            return ['makkah' => $travelers];
        }

        return $hotelAccommodations
            ->mapWithKeys(function (array $accommodation) use ($travelers) {
                $key = Str::slug((string) ($accommodation['location'] ?? $accommodation['hotel_name'] ?? 'hotel'));

                if ($key === '') {
                    $key = 'hotel';
                }

                return [$key => $travelers];
            })
            ->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $travelers
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildRoomListsFromRooms(Manifest $manifest, array $travelers): array
    {
        if ($manifest->rooms->isEmpty()) {
            return [];
        }

        $travelerById = collect($travelers)->keyBy('id');

        return $manifest->rooms
            ->sortBy('sort_order')
            ->groupBy(fn ($room) => (string) ($room->location ?? 'makkah'))
            ->map(function ($rooms) use ($travelerById) {
                return $rooms
                    ->values()
                    ->flatMap(function (ManifestRoom $room) use ($travelerById) {
                        return $room->roomMembers
                            ->sortBy('sort_order')
                            ->values()
                            ->map(function ($member, int $index) use ($room, $travelerById) {
                                $traveler = $travelerById->get($member->manifest_traveler_id, []);

                                return array_merge($traveler, [
                                    'sn' => $member->sort_order ?: ($index + 1),
                                    'sort_order' => $member->sort_order,
                                    'sharing_group_key' => 'room-'.$room->id,
                                    'manifest_traveler_id' => $member->manifest_traveler_id,
                                    'room_relationship' => $room->relationship,
                                    'room_label' => $room->room_label,
                                    'room_number' => $room->room_number,
                                    'room_no' => $room->room_number,
                                    'sharing_plan' => $room->sharing_plan,
                                    'room_type' => $room->room_type,
                                    'bed_type' => $room->bed_type,
                                    'number_of_beds_checked' => (bool) $room->number_of_beds_checked,
                                    'meal' => $room->meal,
                                    'room_remarks' => $room->remarks,
                                    'remarks' => $member->remarks,
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
    private function resolveManifestTravelerId(Manifest $manifest, array $memberPayload): ?int
    {
        if (! empty($memberPayload['customer_confirmation_member_id'])) {
            $travelerId = $manifest->travelers()
                ->where('customer_confirmation_member_id', (int) $memberPayload['customer_confirmation_member_id'])
                ->value('id');

            if ($travelerId) {
                return (int) $travelerId;
            }
        }

        if (! empty($memberPayload['manifest_traveler_id'])) {
            $travelerId = (int) $memberPayload['manifest_traveler_id'];

            $exists = $manifest->travelers()
                ->whereKey($travelerId)
                ->exists();

            if ($exists) {
                return $travelerId;
            }
        }

        if (! empty($memberPayload['package_official_id'])) {
            $travelerId = $manifest->travelers()
                ->where('package_official_id', (int) $memberPayload['package_official_id'])
                ->value('id');

            if ($travelerId) {
                return (int) $travelerId;
            }
        }

        if (! empty($memberPayload['id'])) {
            $travelerId = (int) $memberPayload['id'];

            $exists = $manifest->travelers()
                ->whereKey($travelerId)
                ->exists();

            if ($exists) {
                return $travelerId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $traveler
     */
    private function syncMemberData(CustomerConfirmationMember $member, array $traveler): void
    {
        $memberUpdates = [];

        if (array_key_exists('role', $traveler)) {
            $memberUpdates['role'] = $traveler['role'] ?? null;
        }

        if (array_key_exists('sharing_plan', $traveler)) {
            $memberUpdates['sharing_plan'] = $traveler['sharing_plan'] ?: null;
        }

        if (array_key_exists('status', $traveler)) {
            $memberUpdates['status'] = $traveler['status'] ?: $member->status;
        }

        if ($memberUpdates !== []) {
            $member->update($memberUpdates);
        }
    }

    /**
     * @param  array<string, mixed>  $traveler
     */
    private function syncCustomerData(CustomerConfirmationMember $member, array $traveler): void
    {
        $customer = $member->customer;

        if (! $customer) {
            return;
        }

        $customerUpdates = [];

        if (array_key_exists('passport_number', $traveler) || array_key_exists('passport_no', $traveler)) {
            $customerUpdates['passport_number'] = $traveler['passport_number'] ?? null;
        }

        if (array_key_exists('nationality', $traveler)) {
            $customerUpdates['nationality'] = $traveler['nationality'] ?: null;
        }

        if (array_key_exists('gender', $traveler)) {
            $customerUpdates['gender'] = $traveler['gender'] ?: null;
        }

        if (array_key_exists('issue_place', $traveler)) {
            $customerUpdates['passport_place_of_issue'] = $traveler['issue_place'] ?: null;
        }

        if (array_key_exists('date_of_issue', $traveler)) {
            $customerUpdates['passport_issue_date'] = ! empty($traveler['date_of_issue'])
                ? Carbon::parse($traveler['date_of_issue'])->format('Y-m-d')
                : null;
        }

        if (array_key_exists('date_of_expiry', $traveler)) {
            $customerUpdates['passport_expiry_date'] = ! empty($traveler['date_of_expiry'])
                ? Carbon::parse($traveler['date_of_expiry'])->format('Y-m-d')
                : null;
        }

        if (array_key_exists('date_of_birth', $traveler)) {
            $customerUpdates['date_of_birth'] = ! empty($traveler['date_of_birth'])
                ? Carbon::parse($traveler['date_of_birth'])->format('Y-m-d')
                : null;
        }

        if (array_key_exists('birth_place', $traveler)) {
            $customerUpdates['place_of_birth'] = $traveler['birth_place'] ?: null;
        }

        if (array_key_exists('address', $traveler)) {
            $customerUpdates['address'] = $traveler['address'] ?: null;
        }

        if (array_key_exists('first_time_umrah', $traveler) || array_key_exists('is_first_time_umrah', $traveler)) {
            $customerUpdates['first_time_umrah'] = $traveler['first_time_umrah'] ?? ($traveler['is_first_time_umrah'] ?? null);
        }

        if (array_key_exists('has_chronic_disease', $traveler)) {
            $customerUpdates['has_chronic_disease'] = $traveler['has_chronic_disease'];
        }

        if (array_key_exists('chronic_disease_details', $traveler)) {
            $customerUpdates['chronic_disease_details'] = $traveler['chronic_disease_details'] ?: null;
        }

        if (array_key_exists('passport_path', $traveler)) {
            $customerUpdates['passport_path'] = $traveler['passport_path'] ?: null;
        }

        if (array_key_exists('photo_path', $traveler)) {
            $customerUpdates['photo_path'] = $traveler['photo_path'] ?: null;
        }

        if ($customerUpdates !== []) {
            $customer->update($customerUpdates);
        }

        $name = trim((string) ($traveler['name_as_per_passport'] ?? ''));
        $contactNo = trim((string) ($traveler['contact_no'] ?? ''));

        if ($customer->user && ($name !== '' || $contactNo !== '')) {
            $customer->user->update([
                'name' => $name !== '' ? $name : $customer->user->name,
                'contact' => $contactNo !== '' ? $contactNo : $customer->user->contact,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $traveler
     * @return array<string, mixed>
     */
    private function buildManifestMemberSnapshot(?CustomerConfirmationMember $member, array $traveler, ?PackageOfficial $packageOfficial = null): array
    {
        $customer = $member?->customer;
        $user = $customer?->user;

        return [
            'role' => $traveler['role'] ?? $member?->role,
            'sharing_plan' => $traveler['sharing_plan'] ?? $member?->sharing_plan,
            'name' => $traveler['name_as_per_passport'] ?? $traveler['customer_name'] ?? $packageOfficial?->name ?? $user?->name,
            'arabic_name' => $traveler['arabic_name'] ?? null,
            'contact_number' => $traveler['contact_no'] ?? $packageOfficial?->contact_number ?? $user?->contact,
            'nationality' => $traveler['nationality'] ?? $packageOfficial?->nationality ?? $customer?->nationality,
            'passport_number' => $traveler['passport_number'] ?? $packageOfficial?->passport_number ?? $customer?->passport_number,
            'gender' => $traveler['gender'] ?? $packageOfficial?->gender ?? $customer?->gender,
            'date_of_birth' => $this->normalizeDateForStorage($traveler['date_of_birth'] ?? $packageOfficial?->date_of_birth ?? $customer?->date_of_birth),
            'passport_issue_date' => $this->normalizeDateForStorage($traveler['date_of_issue'] ?? $packageOfficial?->passport_issue_date ?? $customer?->passport_issue_date),
            'passport_expiry_date' => $this->normalizeDateForStorage($traveler['date_of_expiry'] ?? $packageOfficial?->passport_expiry_date ?? $customer?->passport_expiry_date),
            'passport_place_of_issue' => $traveler['issue_place'] ?? $packageOfficial?->passport_place_of_issue ?? $customer?->passport_place_of_issue,
            'place_of_birth' => $traveler['birth_place'] ?? $packageOfficial?->place_of_birth ?? $customer?->place_of_birth,
            'address' => $traveler['address'] ?? $customer?->address,
            'first_time_umrah' => $traveler['first_time_umrah'] ?? $traveler['is_first_time_umrah'] ?? $customer?->first_time_umrah,
            'has_chronic_disease' => $traveler['has_chronic_disease'] ?? $customer?->has_chronic_disease,
            'chronic_disease_details' => $traveler['chronic_disease_details'] ?? $customer?->chronic_disease_details,
            'passport_path' => $traveler['passport_path'] ?? $customer?->passport_path,
            'photo_path' => $traveler['photo_path'] ?? $customer?->photo_path,
        ];
    }

    /**
     * @param  array<string, mixed>  $traveler
     */
    private function syncPackageOfficialData(PackageOfficial $packageOfficial, array $traveler): void
    {
        $updates = [];

        if (array_key_exists('name_as_per_passport', $traveler) || array_key_exists('customer_name', $traveler)) {
            $updates['name'] = $traveler['name_as_per_passport'] ?? $traveler['customer_name'] ?? $packageOfficial->name;
        }

        if (array_key_exists('contact_no', $traveler)) {
            $updates['contact_number'] = $traveler['contact_no'] ?: null;
        }

        if (array_key_exists('nationality', $traveler)) {
            $updates['nationality'] = $traveler['nationality'] ?: null;
        }

        if (array_key_exists('passport_number', $traveler)) {
            $updates['passport_number'] = $traveler['passport_number'] ?: null;
        }

        if (array_key_exists('gender', $traveler)) {
            $updates['gender'] = $traveler['gender'] ?: null;
        }

        if (array_key_exists('date_of_birth', $traveler)) {
            $updates['date_of_birth'] = $this->normalizeDateForStorage($traveler['date_of_birth']);
        }

        if (array_key_exists('date_of_issue', $traveler)) {
            $updates['passport_issue_date'] = $this->normalizeDateForStorage($traveler['date_of_issue']);
        }

        if (array_key_exists('date_of_expiry', $traveler)) {
            $updates['passport_expiry_date'] = $this->normalizeDateForStorage($traveler['date_of_expiry']);
        }

        if (array_key_exists('issue_place', $traveler)) {
            $updates['passport_place_of_issue'] = $traveler['issue_place'] ?: null;
        }

        if (array_key_exists('birth_place', $traveler)) {
            $updates['place_of_birth'] = $traveler['birth_place'] ?: null;
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
    private function buildTravelerFinancialSnapshot(
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
        $allowedFields = ['flight_tickets', 'visa', 'hotel', 'passport', 'photo'];
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

        foreach ($existingFiles->flatten() as $existingFile) {
            if (! in_array($existingFile->file_path, $preservedPaths, true) && $existingFile->file_path) {
                Storage::disk('public')->delete($existingFile->file_path);
            }
        }

        $manifest->files()->delete();

        foreach ($rowsToPersist as $row) {
            $manifest->files()->create($row);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $travelers
     */
    private function syncTravelerReceiptDocuments(Manifest $manifest, array $travelers): void
    {
        if ($travelers === []) {
            return;
        }

        $manifestTravelersByConfirmationMember = $manifest->travelers()
            ->whereNotNull('customer_confirmation_member_id')
            ->get()
            ->keyBy('customer_confirmation_member_id');

        foreach ($travelers as $travelerPayload) {
            if (! is_array($travelerPayload)) {
                continue;
            }

            $confirmationMemberId = isset($travelerPayload['customer_confirmation_member_id'])
                ? (int) $travelerPayload['customer_confirmation_member_id']
                : 0;

            if ($confirmationMemberId <= 0) {
                continue;
            }

            /** @var ManifestMember|null $manifestTraveler */
            $manifestTraveler = $manifestTravelersByConfirmationMember->get($confirmationMemberId);

            if (! $manifestTraveler) {
                continue;
            }

            $receiptDocuments = $travelerPayload['receipt_documents'] ?? [];

            if (! is_array($receiptDocuments)) {
                continue;
            }

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

            $existingReceiptFiles = $manifestTraveler->files()->where('field', 'receipt')->get();
            $preservedPaths = collect($rowsToPersist)
                ->pluck('file_path')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->all();

            foreach ($existingReceiptFiles as $existingReceiptFile) {
                if (! in_array($existingReceiptFile->file_path, $preservedPaths, true) && $existingReceiptFile->file_path) {
                    Storage::disk('public')->delete($existingReceiptFile->file_path);
                }
            }

            $manifestTraveler->files()->where('field', 'receipt')->delete();

            foreach ($rowsToPersist as $row) {
                $manifestTraveler->files()->create($row);
            }
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildManifestDocumentPayload(Manifest $manifest): array
    {
        $allowedFields = ['flight_tickets', 'visa', 'hotel', 'passport', 'photo'];
        $grouped = $manifest->files->groupBy('field');
        $documents = [];

        foreach ($allowedFields as $field) {
            $documents[$field] = ($grouped->get($field) ?? collect())
                ->map(function (ModelFile $file): array {
                    return [
                        'id' => $file->id,
                        'file_name' => $file->file_name,
                        'file_path' => $file->file_path,
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
