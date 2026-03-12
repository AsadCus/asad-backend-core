<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\ManifestPayment;
use App\Models\ManifestRoom;
use App\Models\ManifestSharingGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
                'travelers as travelers_count',
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
            'travelers.confirmationMember.confirmation.enquiry',
            'travelers.confirmationMember.customer.user',
            'rooms.roomMembers.traveler',
            'rooms.manifestSharingGroups.sharingGroup',
            'payments.traveler',
            'manifestSharingGroups.sharingGroup.members.confirmationMember.customer',
        ])->findOrFail($id);

        $travelers = $manifest->travelers
            ->sortBy('id')
            ->values()
            ->map(function ($traveler, $index) {
                $member = $traveler->confirmationMember;
                $confirmation = $member?->confirmation;
                $enquiryCreatedAt = $confirmation?->enquiry?->created_at;
                $customer = $member?->customer;
                $user = $customer?->user;

                return [
                    'id' => $traveler->id,
                    'sn' => $index + 1,
                    'customer_id' => $customer?->id,
                    'customer_confirmation_member_id' => $traveler->customer_confirmation_member_id,
                    'customer_confirmation_id' => $member?->customer_confirmation_id,
                    'customer_name' => $user?->name,
                    'name_as_per_passport' => $user?->name,
                    'role' => $member?->role,
                    'relationship' => $member?->role,
                    'sharing_plan' => $member?->sharing_plan,
                    'package_category' => $confirmation?->package_category,
                    'date_of_sign_up' => ($enquiryCreatedAt ?? $confirmation?->created_at)?->translatedFormat('d F Y'),
                    'is_first_time_umrah' => $customer?->first_time_umrah,
                    'passport_number' => $customer?->passport_number,
                    'nationality' => $customer?->nationality,
                    'gender' => $customer?->gender,
                    'date_of_issue' => $customer?->passport_issue_date_formatted,
                    'date_of_expiry' => $customer?->passport_expiry_date_formatted,
                    'issue_place' => $customer?->passport_place_of_issue,
                    'date_of_birth' => $customer?->date_of_birth_formatted,
                    'age' => $customer?->date_of_birth?->age,
                    'remarks' => $traveler->remarks,
                    'status' => $member?->status ?? 'draft',
                ];
            })
            ->toArray();

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
            'package_name' => $manifest->package?->name,
            'manifest_number' => $manifest->manifest_number,
            'departure_date' => $manifest->package?->departure_date_formatted,
            'return_date' => $manifest->package?->return_date_formatted,
            'notes' => $manifest->notes,
            'status' => $manifest->status,
            'travelers' => $travelers,
            'roomLists' => $roomLists,
            'airlineList' => $airlineList,
            'rooms' => $manifest->rooms->map(function ($r) {
                return [
                    'id' => $r->id,
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
                    'remarks' => $r->remarks,
                    'members' => $r->roomMembers->map(function ($rm) {
                        $member = $rm->traveler?->confirmationMember;
                        $customer = $member?->customer;

                        return [
                            'id' => $rm->id,
                            'manifest_traveler_id' => $rm->manifest_traveler_id,
                            'traveler_name' => $customer?->user?->name,
                            'role_in_room' => $member?->role,
                            'sort_order' => $rm->sort_order,
                            'remarks' => $rm->remarks,
                            'customer_confirmation_member_id' => $member?->id,
                            'customer_id' => $customer?->id,
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
                            'passport_issue_date' => $customer?->passport_issue_date_formatted ?? '',
                            'passport_expiry_date' => $customer?->passport_expiry_date_formatted ?? '',
                            'passport_place_of_issue' => $customer?->passport_place_of_issue ?? '',
                            'date_of_birth' => $customer?->date_of_birth_formatted ?? '',
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

        foreach ($flatTravelers as $traveler) {
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

            $manifest->travelers()->create([
                'customer_confirmation_member_id' => $member?->id,
                'remarks' => $traveler['remarks'] ?? null,
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
            $sharingPlan = isset($room['sharing_plan']) && is_string($room['sharing_plan'])
                ? strtolower(trim($room['sharing_plan']))
                : null;

            $roomType = isset($room['room_type']) && is_string($room['room_type'])
                ? strtolower(trim($room['room_type']))
                : null;

            $bedType = isset($room['bed_type']) && is_string($room['bed_type'])
                ? strtolower(trim($room['bed_type']))
                : null;

            $createdRoom = $manifest->rooms()->create([
                'location' => $room['location'] ?? null,
                'relationship' => $room['relationship'] ?? null,
                'room_label' => $room['room_label'] ?? null,
                'room_number' => $room['room_number'] ?? $room['room_no'] ?? null,
                'room_type' => $roomType,
                'bed_type' => $bedType,
                'capacity' => $room['capacity'] ?? (isset($room['members']) && is_array($room['members']) ? count($room['members']) : null),
                'sharing_plan' => $sharingPlan,
                'status' => $room['status'] ?? 'pending',
                'meal' => $room['meal'] ?? null,
                'remarks' => $room['remarks'] ?? null,
            ]);

            if (! empty($room['members'])) {
                foreach ($room['members'] as $index => $member) {
                    $manifestTravelerId = $this->resolveManifestTravelerId($manifest, $member);

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
            'mekkah' => $this->flattenGroupedData(is_array($flightDetails['ui_room_list_mekkah'] ?? null) ? $flightDetails['ui_room_list_mekkah'] : []),
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
            return ['mekkah' => $travelers];
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
            ->groupBy(fn ($room) => (string) ($room->location ?? 'mekkah'))
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

            return $exists ? $travelerId : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $traveler
     */
    private function syncMemberData(CustomerConfirmationMember $member, array $traveler): void
    {
        $memberUpdates = [];

        if (array_key_exists('role', $traveler) || array_key_exists('relationship', $traveler)) {
            $memberUpdates['role'] = $traveler['role'] ?? $traveler['relationship'] ?? null;
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
            $customerUpdates['passport_number'] = $traveler['passport_number'] ?? $traveler['passport_no'] ?? null;
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
                ? Carbon::parse($traveler['date_of_issue'])->translatedFormat('d F Y')
                : null;
        }

        if (array_key_exists('date_of_expiry', $traveler)) {
            $customerUpdates['passport_expiry_date'] = ! empty($traveler['date_of_expiry'])
                ? Carbon::parse($traveler['date_of_expiry'])->translatedFormat('d F Y')
                : null;
        }

        if (array_key_exists('date_of_birth', $traveler)) {
            $customerUpdates['date_of_birth'] = ! empty($traveler['date_of_birth'])
                ? Carbon::parse($traveler['date_of_birth'])->translatedFormat('d F Y')
                : null;
        }

        if ($customerUpdates !== []) {
            $customer->update($customerUpdates);
        }

        $name = trim((string) ($traveler['name_as_per_passport'] ?? ''));
        if ($name !== '' && $customer->user) {
            $customer->user->update(['name' => $name]);
        }
    }
}
