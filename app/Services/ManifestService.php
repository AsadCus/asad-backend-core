<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\CustomerConfirmation;
use App\Models\Manifest;
use App\Models\ManifestAccommodationAssignment;
use App\Models\ManifestPayment;
use App\Models\ManifestRoom;
use App\Models\ManifestSharingGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
                'travelers as travelers_count' => function ($query) {
                    $query->where('status', '!=', 'cancelled');
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
                $q->where('status', $value);
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
                    'status' => $q->status,
                    'travelers_count' => $q->travelers_count,
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
                'manifest_number' => NumberGenerator::generate('manifest'),
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'draft',
            ]);

            $this->syncTravelers($manifest, $data['travelers'] ?? []);
            $this->syncAccommodationAssignments(
                $manifest,
                data_get($data, 'roomLists', []),
            );
            $this->syncRooms($manifest, $data['rooms'] ?? []);
            $this->syncPayments($manifest, $data['payments'] ?? []);
            $this->syncSharingGroups($manifest, $data['sharing_group_ids'] ?? []);

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
            'travelers.customer',
            'travelers.confirmationMember',
            'accommodationAssignments',
            'rooms.roomMembers.traveler',
            'rooms.manifestSharingGroups.sharingGroup',
            'payments.traveler',
            'manifestSharingGroups.sharingGroup.members.confirmationMember.customer',
        ])->findOrFail($id);

        $travelers = $manifest->travelers
            ->sortBy('sn')
            ->values()
            ->map(function ($traveler) {
                $isOfficialTraveler = $traveler->customer_confirmation_member_id === null
                    && ($traveler->relationship === 'official'
                        || str_starts_with((string) ($traveler->remarks ?? ''), '[package-official]'));

                $memberStatus = $traveler->confirmationMember?->status;

                $resolvedStatus = $traveler->status ?? 'confirmed';

                if ($traveler->customer_confirmation_member_id !== null) {
                    $resolvedStatus = $memberStatus ?? $resolvedStatus;
                } elseif ($isOfficialTraveler && $resolvedStatus !== 'cancelled') {
                    $resolvedStatus = 'confirmed';
                }

                return [
                    'id' => $traveler->id,
                    'sn' => $traveler->sn,
                    'customer_id' => $traveler->customer_id,
                    'customer_confirmation_member_id' => $traveler->customer_confirmation_member_id,
                    'customer_confirmation_id' => $traveler->confirmationMember?->customer_confirmation_id,
                    'customer_name' => $traveler->customer?->name,
                    'name_as_per_passport' => $traveler->name_as_per_passport,
                    'role' => $traveler->confirmationMember?->role ?? $traveler->relationship,
                    'relationship' => $traveler->relationship,
                    'sharing_plan' => $traveler->confirmationMember?->sharing_plan ?? ($isOfficialTraveler ? 'single' : null),
                    'passport_no' => $traveler->passport_no ?? $traveler->confirmationMember?->customer?->passport_number,
                    'ppt_no' => $traveler->passport_no ?? $traveler->confirmationMember?->customer?->passport_number,
                    'nationality' => $traveler->confirmationMember?->customer?->nationality,
                    'gender' => $traveler->confirmationMember?->customer?->gender,
                    'date_of_issue' => $traveler->confirmationMember?->customer?->passport_issue_date_formatted,
                    'date_of_expiry' => $traveler->confirmationMember?->customer?->passport_expiry_date_formatted,
                    'issue_place' => $traveler->confirmationMember?->customer?->passport_place_of_issue,
                    'room_no' => $traveler->room_no,
                    'room_type' => $traveler->room_type,
                    'bed_type' => $traveler->bed_type,
                    'date_of_birth' => $traveler->date_of_birth_formatted ?? $traveler->confirmationMember?->customer?->date_of_birth_formatted,
                    'age' => $traveler->age,
                    'no_of_beds_checked' => $traveler->no_of_beds_checked,
                    'meal' => $traveler->meal,
                    'remarks' => $traveler->remarks,
                    'total_cost' => $traveler->total_cost,
                    'total_paid' => $traveler->total_paid,
                    'outstanding_amount' => $traveler->outstanding_amount,
                    'status' => $resolvedStatus,
                ];
            })
            ->toArray();

        $roomLists = $this->buildRoomListsFromAssignments($manifest, $travelers);

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
            'package_name' => $manifest->package?->name,
            'manifest_number' => $manifest->manifest_number,
            'departure_date' => $manifest->package?->departure_date_formatted,
            'return_date' => $manifest->package?->return_date_formatted,
            'notes' => $manifest->notes,
            'status' => $manifest->status,
            'travelers' => $travelers,
            'roomLists' => $roomLists,
            'roomListMakkah' => $roomLists['makkah'] ?? [],
            'roomListMadinah' => $roomLists['madinah'] ?? [],
            'roomListOthers' => $roomLists['others'] ?? [],
            'airlineList' => $airlineList,
            'rooms' => $manifest->rooms->map(function ($r) {
                return [
                    'id' => $r->id,
                    'location' => $r->location,
                    'room_number' => $r->room_number,
                    'room_type' => $r->room_type,
                    'bed_type' => $r->bed_type,
                    'capacity' => $r->capacity,
                    'sharing_plan' => $r->sharing_plan,
                    'status' => $r->status,
                    'room_label' => $r->room_label,
                    'members' => $r->roomMembers->map(function ($rm) {
                        return [
                            'id' => $rm->id,
                            'manifest_traveler_id' => $rm->manifest_traveler_id,
                            'traveler_name' => $rm->traveler?->name_as_per_passport,
                            'role_in_room' => $rm->role_in_room,
                        ];
                    })->toArray(),
                    'sharing_groups' => $r->manifestSharingGroups->map(function ($msg) {
                        return [
                            'id' => $msg->id,
                            'sharing_group_id' => $msg->sharing_group_id,
                            'sharing_plan' => $msg->sharingGroup?->sharing_plan,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
            'payments' => $manifest->payments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'manifest_traveler_id' => $p->manifest_traveler_id,
                    'traveler_name' => $p->traveler_name,
                    'linked_traveler_name' => $p->traveler?->name_as_per_passport,
                    'description' => $p->description,
                    'amount' => $p->amount,
                    'paid_amount' => $p->paid_amount,
                    'outstanding_amount' => $p->outstanding_amount,
                    'payment_date' => $p->payment_date_formatted,
                    'status' => $p->status,
                ];
            })->toArray(),
            'sharing_groups' => $manifest->manifestSharingGroups->map(function ($msg) {
                $sg = $msg->sharingGroup;

                return [
                    'id' => $msg->id,
                    'sharing_group_id' => $sg?->id,
                    'manifest_room_id' => $msg->manifest_room_id,
                    'sharing_plan' => $sg?->sharing_plan,
                    'expected_capacity' => $sg?->expected_capacity,
                    'status' => $sg?->status,
                    'customer_confirmation_id' => $sg?->customer_confirmation_id,
                    'remarks' => $sg?->remarks,
                    'members' => $sg?->members->map(function ($m) {
                        $cm = $m->confirmationMember;

                        return [
                            'id' => $m->id,
                            'customer_confirmation_member_id' => $m->customer_confirmation_member_id,
                            'role_in_group' => $m->role_in_group,
                            'sort_order' => $m->sort_order,
                            'customer_name' => $cm?->customer?->name,
                            'customer_id' => $cm?->customer_id,
                        ];
                    })->toArray() ?? [],
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
                'notes' => $data['notes'] ?? $manifest->notes,
                'status' => $data['status'] ?? $manifest->status,
            ]);

            if (isset($data['travelers'])) {
                $this->syncTravelers($manifest, $data['travelers']);
            }

            $this->syncAccommodationAssignments(
                $manifest,
                data_get($data, 'roomLists', []),
            );

            if (isset($data['rooms'])) {
                $this->syncRooms($manifest, $data['rooms']);
            }

            if (isset($data['payments'])) {
                $this->syncPayments($manifest, $data['payments']);
            }

            if (isset($data['sharing_group_ids'])) {
                $this->syncSharingGroups($manifest, $data['sharing_group_ids']);
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
                'location' => $data['location'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'bed_type' => $data['bed_type'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'sharing_plan' => $data['sharing_plan'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'room_label' => $data['room_label'] ?? null,
            ]);

            if (! empty($data['members'])) {
                foreach ($data['members'] as $member) {
                    $room->roomMembers()->create([
                        'manifest_traveler_id' => $member['manifest_traveler_id'],
                        'role_in_room' => $member['role_in_room'] ?? null,
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
                'location' => $data['location'] ?? $room->location,
                'room_number' => $data['room_number'] ?? $room->room_number,
                'room_type' => $data['room_type'] ?? $room->room_type,
                'bed_type' => $data['bed_type'] ?? $room->bed_type,
                'capacity' => $data['capacity'] ?? $room->capacity,
                'sharing_plan' => $data['sharing_plan'] ?? $room->sharing_plan,
                'status' => $data['status'] ?? $room->status,
                'room_label' => $data['room_label'] ?? $room->room_label,
            ]);

            if (isset($data['members'])) {
                $room->roomMembers()->delete();
                foreach ($data['members'] as $member) {
                    $room->roomMembers()->create([
                        'manifest_traveler_id' => $member['manifest_traveler_id'],
                        'role_in_room' => $member['role_in_room'] ?? null,
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
        $room->manifestSharingGroups()->update(['manifest_room_id' => null]);
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
    public function attachSharingGroup(int $manifestId, int $sharingGroupId, ?int $manifestRoomId = null): ManifestSharingGroup
    {
        $manifest = Manifest::findOrFail($manifestId);

        $msg = ManifestSharingGroup::firstOrCreate([
            'manifest_id' => $manifestId,
            'sharing_group_id' => $sharingGroupId,
        ], [
            'manifest_room_id' => $manifestRoomId,
        ]);

        if ($manifestRoomId && $msg->manifest_room_id !== $manifestRoomId) {
            $msg->update(['manifest_room_id' => $manifestRoomId]);
        }

        activity()
            ->performedOn($manifest)
            ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifestId])
            ->log('Sharing group #'.$sharingGroupId.' attached to manifest #'.$manifestId);

        return $msg;
    }

    /**
     * Detach a sharing group from a manifest.
     */
    public function detachSharingGroup(int $manifestId, int $sharingGroupId): bool
    {
        $deleted = ManifestSharingGroup::where('manifest_id', $manifestId)
            ->where('sharing_group_id', $sharingGroupId)
            ->delete();

        if ($deleted) {
            activity()
                ->performedOn(Manifest::find($manifestId))
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifestId])
                ->log('Sharing group #'.$sharingGroupId.' detached from manifest #'.$manifestId);
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
            'sharingGroups.members.confirmationMember.customer.user',
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
                            'passport_issue_date' => $customer?->passport_issue_date?->format('d/m/Y') ?? '',
                            'passport_expiry_date' => $customer?->passport_expiry_date?->format('d/m/Y') ?? '',
                            'passport_place_of_issue' => $customer?->passport_place_of_issue ?? '',
                            'date_of_birth' => $customer?->date_of_birth?->format('d/m/Y') ?? '',
                            'age' => $customer?->date_of_birth ? $customer->date_of_birth->age : null,
                        ];
                    })->values()->all(),
                    'sharing_groups' => $confirmation->sharingGroups->map(function ($sg) {
                        return [
                            'id' => $sg->id,
                            'sharing_plan' => $sg->sharing_plan,
                            'expected_capacity' => $sg->expected_capacity,
                            'status' => $sg->status,
                            'sort_order' => $sg->sort_order,
                            'remarks' => $sg->remarks,
                            'members' => $sg->members->map(function ($m) {
                                return [
                                    'id' => $m->id,
                                    'customer_confirmation_member_id' => $m->customer_confirmation_member_id,
                                    'role_in_group' => $m->role_in_group,
                                    'sort_order' => $m->sort_order,
                                    'customer_name' => $m->confirmationMember?->customer?->user?->name ?? '-',
                                ];
                            })->all(),
                        ];
                    })->all(),
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
        $manifest->travelers()->delete();

        $flatTravelers = $this->flattenGroupedData($travelers);

        foreach ($flatTravelers as $index => $traveler) {
            $manifest->travelers()->create([
                'sn' => $traveler['sn'] ?? ($index + 1),
                'customer_id' => $traveler['customer_id'] ?? null,
                'customer_confirmation_member_id' => $traveler['customer_confirmation_member_id'] ?? null,
                'name_as_per_passport' => $traveler['name_as_per_passport'] ?? null,
                'relationship' => $traveler['role'] ?? $traveler['relationship'] ?? null,
                'passport_no' => $traveler['passport_no'] ?? null,
                'room_no' => $traveler['room_no'] ?? null,
                'room_type' => $traveler['room_type'] ?? null,
                'bed_type' => $traveler['bed_type'] ?? null,
                'date_of_birth' => ! empty($traveler['date_of_birth']) ? Carbon::parse($traveler['date_of_birth'])->format('Y-m-d') : null,
                'age' => $traveler['age'] ?? null,
                'no_of_beds_checked' => $traveler['no_of_beds_checked'] ?? null,
                'meal' => $traveler['meal'] ?? null,
                'remarks' => $traveler['remarks'] ?? null,
                'total_cost' => $traveler['total_cost'] ?? 0,
                'total_paid' => $traveler['total_paid'] ?? 0,
                'outstanding_amount' => $traveler['outstanding_amount'] ?? 0,
                'status' => $traveler['status']
                    ?? (! empty($traveler['customer_confirmation_member_id']) ? 'pending_payment' : 'confirmed'),
            ]);
        }
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

        foreach ($flatRooms as $room) {
            $createdRoom = $manifest->rooms()->create([
                'location' => $room['location'] ?? null,
                'room_number' => $room['room_number'] ?? null,
                'room_type' => $room['room_type'] ?? null,
                'bed_type' => $room['bed_type'] ?? null,
                'capacity' => $room['capacity'] ?? null,
                'sharing_plan' => $room['sharing_plan'] ?? null,
                'status' => $room['status'] ?? 'pending',
                'room_label' => $room['room_label'] ?? null,
            ]);

            if (! empty($room['members'])) {
                foreach ($room['members'] as $member) {
                    $createdRoom->roomMembers()->create([
                        'manifest_traveler_id' => $member['manifest_traveler_id'],
                        'role_in_room' => $member['role_in_room'] ?? null,
                    ]);
                }
            }
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
     * Sync sharing groups attached to a manifest.
     *
     * @param  array<int>  $sharingGroupIds
     */
    private function syncSharingGroups(Manifest $manifest, array $sharingGroupIds): void
    {
        $manifest->manifestSharingGroups()->delete();

        foreach ($sharingGroupIds as $sgId) {
            ManifestSharingGroup::create([
                'manifest_id' => $manifest->id,
                'sharing_group_id' => $sgId,
                'manifest_room_id' => null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $roomLists
     */
    private function syncAccommodationAssignments(Manifest $manifest, array $roomLists): void
    {
        $manifest->accommodationAssignments()->delete();

        if ($roomLists === []) {
            return;
        }

        $travelers = $manifest->travelers()->get();
        $travelerByMemberId = $travelers
            ->filter(fn ($traveler) => ! empty($traveler->customer_confirmation_member_id))
            ->keyBy('customer_confirmation_member_id');
        $travelerByCustomerId = $travelers
            ->filter(fn ($traveler) => ! empty($traveler->customer_id))
            ->keyBy('customer_id');

        foreach ($roomLists as $accommodationKey => $rows) {
            $flatRows = $this->flattenGroupedData(is_array($rows) ? $rows : []);

            foreach ($flatRows as $index => $row) {
                $memberId = $row['customer_confirmation_member_id'] ?? null;
                $customerId = $row['customer_id'] ?? null;

                $matchedTraveler = null;
                if ($memberId && $travelerByMemberId->has($memberId)) {
                    $matchedTraveler = $travelerByMemberId->get($memberId);
                } elseif ($customerId && $travelerByCustomerId->has($customerId)) {
                    $matchedTraveler = $travelerByCustomerId->get($customerId);
                }

                ManifestAccommodationAssignment::create([
                    'manifest_id' => $manifest->id,
                    'manifest_traveler_id' => $matchedTraveler?->id,
                    'customer_id' => $customerId,
                    'customer_confirmation_member_id' => $memberId,
                    'accommodation_key' => (string) $accommodationKey,
                    'sort_order' => (int) ($row['sort_order'] ?? $row['sn'] ?? ($index + 1)),
                    'sharing_group_key' => $row['sharing_group_key'] ?? null,
                    'room_no' => $row['room_no'] ?? null,
                    'room_type' => $row['room_type'] ?? null,
                    'bed_type' => $row['bed_type'] ?? null,
                    'meal' => $row['meal'] ?? null,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }
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
                $key = strtolower((string) ($accommodation['location'] ?? $accommodation['hotel_name'] ?? 'hotel'));

                return [$key => $travelers];
            })
            ->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $travelers
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildRoomListsFromAssignments(Manifest $manifest, array $travelers): array
    {
        if ($manifest->accommodationAssignments->isEmpty()) {
            return [];
        }

        $travelerById = collect($travelers)->keyBy('id');
        $travelerByMemberId = collect($travelers)
            ->filter(fn (array $traveler) => ! empty($traveler['customer_confirmation_member_id']))
            ->keyBy('customer_confirmation_member_id');
        $travelerByCustomerId = collect($travelers)
            ->filter(fn (array $traveler) => ! empty($traveler['customer_id']))
            ->keyBy('customer_id');

        return $manifest->accommodationAssignments
            ->sortBy('sort_order')
            ->groupBy('accommodation_key')
            ->map(function ($assignments) use ($travelerById, $travelerByMemberId, $travelerByCustomerId) {
                return $assignments
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function (ManifestAccommodationAssignment $assignment, int $index) use ($travelerById, $travelerByMemberId, $travelerByCustomerId) {
                        $traveler = $travelerById->get($assignment->manifest_traveler_id)
                            ?? $travelerByMemberId->get($assignment->customer_confirmation_member_id)
                            ?? $travelerByCustomerId->get($assignment->customer_id)
                            ?? [];

                        return array_merge($traveler, [
                            'sn' => $assignment->sort_order ?: ($index + 1),
                            'sort_order' => $assignment->sort_order,
                            'sharing_group_key' => $assignment->sharing_group_key,
                            'room_no' => $assignment->room_no,
                            'room_type' => $assignment->room_type,
                            'bed_type' => $assignment->bed_type,
                            'meal' => $assignment->meal,
                            'remarks' => $assignment->remarks,
                            'customer_id' => $assignment->customer_id ?? ($traveler['customer_id'] ?? null),
                            'customer_confirmation_member_id' => $assignment->customer_confirmation_member_id
                                ?? ($traveler['customer_confirmation_member_id'] ?? null),
                        ]);
                    })
                    ->toArray();
            })
            ->toArray();
    }
}
