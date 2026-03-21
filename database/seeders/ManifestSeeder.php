<?php

namespace Database\Seeders;

use App\Helpers\NumberGenerator;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ManifestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = Package::query()->with('accommodations')->orderBy('id')->get();

        if ($packages->isEmpty()) {
            $this->command->warn('No packages found. Please run PackageSeeder and EnquirySeeder first.');

            return;
        }

        foreach ($packages as $package) {
            $manifest = Manifest::query()->firstOrCreate(
                ['package_id' => $package->id],
                [
                    'manifest_number' => NumberGenerator::generate('manifest'),
                    'notes' => 'Seeded manifest for package workflow validation.',
                ]
            );

            $paidMembers = CustomerConfirmationMember::query()
                ->whereHas('confirmation', function ($query) use ($package): void {
                    $query->where('package_id', $package->id);
                })
                ->whereHas('receiptAllocations', function ($query): void {
                    $query->where('allocated_amount', '>', 0);
                })
                ->with(['customer.user'])
                ->orderBy('id')
                ->get();

            $paidMembers->each(function (CustomerConfirmationMember $member): void {
                if (! empty($member->role)) {
                    return;
                }

                $role = $this->resolveMemberRole($member);

                if ($role !== null) {
                    $member->update(['role' => $role]);
                }
            });

            $manifest->members()->delete();
            $manifest->manifestSharingGroups()->delete();

            $membersByManifestGroup = $paidMembers
                ->groupBy(function (CustomerConfirmationMember $member): string {
                    $sharingPlan = strtolower(trim((string) ($member->sharing_plan ?: 'single')));

                    return $member->customer_confirmation_id.'|'.$sharingPlan;
                })
                ->values();

            foreach ($membersByManifestGroup as $groupIndex => $members) {
                /** @var Collection<int, CustomerConfirmationMember> $members */
                $firstMember = $members->first();

                $groupRelation = $this->resolveGroupRelation($members);

                $manifestSharingGroup = $manifest->manifestSharingGroups()->create([
                    'customer_confirmation_id' => $firstMember?->customer_confirmation_id,
                    'sort_order' => $groupIndex + 1,
                    'relation' => $groupRelation ?? $firstMember?->role,
                    'remarks' => null,
                ]);

                foreach ($members->values() as $memberIndex => $member) {
                    $manifest->members()->create([
                        'manifest_sharing_group_id' => $manifestSharingGroup->id,
                        'customer_confirmation_member_id' => $member->id,
                        'sort_order' => $memberIndex + 1,
                        'remarks' => null,
                    ]);
                }
            }

            $manifest->load(['members', 'rooms.roomMembers']);

            foreach ($manifest->rooms as $room) {
                $room->roomMembers()->delete();
            }
            $manifest->rooms()->delete();

            $membersByMember = $manifest->members
                ->filter(fn ($member) => ! empty($member->customer_confirmation_member_id))
                ->keyBy('customer_confirmation_member_id');

            $defaultLocation = optional($package->accommodations->first())->location ?? 'makkah';
            $defaultMeal = optional($package->accommodations->first())->type_of_meal;
            $roomCounter = 1;

            $membersByConfirmationAndPlan = $paidMembers
                ->groupBy(function ($member): string {
                    $sharingPlan = strtolower(trim((string) ($member->sharing_plan ?: 'single')));

                    return $member->customer_confirmation_id.'|'.$sharingPlan;
                });

            foreach ($membersByConfirmationAndPlan as $members) {
                /** @var Collection<int, CustomerConfirmationMember> $members */
                $firstMember = $members->first();
                if (! $firstMember) {
                    continue;
                }

                $sharingPlan = strtolower(trim((string) ($firstMember->sharing_plan ?: 'single')));
                $capacity = $this->capacityFromSharingPlan($sharingPlan);

                if ($capacity < 1) {
                    continue;
                }

                $chunks = $members->chunk($capacity);

                foreach ($chunks as $chunk) {
                    if ($sharingPlan === 'single' && $chunk->count() !== 1) {
                        continue;
                    }

                    $room = $manifest->rooms()->create([
                        'sort_order' => $roomCounter,
                        'location' => $defaultLocation,
                        'relationship' => $firstMember?->role,
                        'room_label' => 'Room '.$roomCounter,
                        'room_number' => null,
                        'room_type' => $sharingPlan,
                        'bed_type' => $this->bedTypeFromRoomType($sharingPlan),
                        'capacity' => $capacity,
                        'sharing_plan' => $sharingPlan,
                        'status' => 'pending',
                        'meal' => $defaultMeal,
                        'remarks' => null,
                    ]);

                    foreach ($chunk->values() as $index => $member) {
                        $member = $membersByMember->get($member->id);

                        if (! $member) {
                            continue;
                        }

                        $room->roomMembers()->create([
                            'manifest_member_id' => $member->id,
                            'sort_order' => $index + 1,
                            'remarks' => null,
                        ]);
                    }

                    $roomCounter++;
                }
            }

            $this->syncPackageOfficialsIntoManifest($manifest, $package);
        }

        $this->command->info('Manifests seeded successfully (with paid member assignments).');
    }

    private function capacityFromSharingPlan(string $sharingPlan): int
    {
        return match ($sharingPlan) {
            'quad' => 4,
            'triple' => 3,
            'double' => 2,
            default => 1,
        };
    }

    private function bedTypeFromRoomType(string $roomType): string
    {
        return match ($roomType) {
            'double', 'quad' => 'king',
            default => 'single',
        };
    }

    private function syncPackageOfficialsIntoManifest(Manifest $manifest, Package $package): void
    {
        $officialMemberMarker = '[package-official]';
        $officialGroupMarker = '[package-official-group]';
        $officialRoomMarker = '[package-official-room]';

        $manifest->members()
            ->whereNull('customer_confirmation_member_id')
            ->where('remarks', 'like', $officialMemberMarker.'%')
            ->delete();

        $manifest->manifestSharingGroups()
            ->where('remarks', 'like', $officialGroupMarker.'%')
            ->delete();

        $manifest->rooms()
            ->where('remarks', 'like', $officialRoomMarker.'%')
            ->each(function ($room): void {
                $room->roomMembers()->delete();
                $room->delete();
            });

        $package->loadMissing(['officials', 'accommodations']);

        $createdOfficialMembers = collect();

        $groupSortOrder = ((int) $manifest->manifestSharingGroups()->max('sort_order')) + 1;

        foreach ($package->officials as $official) {
            $officialGroup = $manifest->manifestSharingGroups()->create([
                'customer_confirmation_id' => null,
                'sort_order' => $groupSortOrder,
                'relation' => 'official',
                'remarks' => $officialGroupMarker,
            ]);

            $createdOfficialMembers->push($manifest->members()->create([
                'manifest_sharing_group_id' => $officialGroup->id,
                'package_official_id' => $official->id,
                'role' => $official->type,
                'sharing_plan' => 'single',
                'name' => $official->name,
                'contact_number' => $official->contact_number,
                'nationality' => $official->nationality,
                'passport_number' => $official->passport_number,
                'gender' => $official->gender,
                'date_of_birth' => $official->date_of_birth,
                'passport_issue_date' => $official->passport_issue_date,
                'passport_expiry_date' => $official->passport_expiry_date,
                'passport_place_of_issue' => $official->passport_place_of_issue,
                'place_of_birth' => $official->place_of_birth,
                'remarks' => $officialMemberMarker.' '.($official->type ?? 'official'),
            ]));

            $groupSortOrder++;
        }

        if ($createdOfficialMembers->isEmpty()) {
            return;
        }

        $locations = $package->accommodations
            ->filter(fn ($accommodation) => ! empty($accommodation->hotel_name))
            ->map(function ($accommodation) {
                $location = (string) ($accommodation->location ?? $accommodation->hotel_name ?? 'makkah');

                return [
                    'key' => \Illuminate\Support\Str::slug($location) ?: 'makkah',
                    'meal' => $accommodation->type_of_meal,
                ];
            })
            ->unique('key')
            ->values();

        if ($locations->isEmpty()) {
            $locations = collect([
                ['key' => 'makkah', 'meal' => null],
            ]);
        }

        $roomSortOrder = 1;

        foreach ($locations as $location) {
            foreach ($createdOfficialMembers as $index => $officialMember) {
                $room = $manifest->rooms()->create([
                    'sort_order' => $roomSortOrder,
                    'location' => $location['key'],
                    'relationship' => 'official',
                    'room_label' => 'Official Room '.($index + 1),
                    'room_type' => 'single',
                    'bed_type' => 'single',
                    'capacity' => 1,
                    'sharing_plan' => 'single',
                    'status' => 'pending',
                    'meal' => $location['meal'],
                    'number_of_beds_checked' => false,
                    'remarks' => $officialRoomMarker,
                ]);

                $room->roomMembers()->create([
                    'manifest_member_id' => $officialMember->id,
                    'sort_order' => 1,
                    'is_assigned' => true,
                ]);

                $roomSortOrder++;
            }
        }
    }

    private function resolveMemberRole(CustomerConfirmationMember $member): ?string
    {
        $customer = $member->customer;
        $gender = strtolower((string) ($customer?->gender ?? ''));
        $age = $customer?->date_of_birth?->age;

        if ($age !== null && $age < 13) {
            if ($gender === 'male') {
                return 'son';
            }

            if ($gender === 'female') {
                return 'daughter';
            }

            return 'child';
        }

        if ($age !== null && $age < 18) {
            return 'child';
        }

        if ($member->is_leader) {
            if ($gender === 'male') {
                return 'husband';
            }

            if ($gender === 'female') {
                return 'wife';
            }
        }

        return 'friend';
    }

    /**
     * @param  Collection<int, CustomerConfirmationMember>  $members
     */
    private function resolveGroupRelation(Collection $members): ?string
    {
        $roles = $members
            ->map(function (CustomerConfirmationMember $member): string {
                return strtolower((string) ($member->role ?: $this->resolveMemberRole($member)));
            })
            ->filter()
            ->values();

        if ($roles->isEmpty()) {
            return null;
        }

        $roleSet = $roles->unique()->values()->all();
        $hasHusband = in_array('husband', $roleSet, true);
        $hasWife = in_array('wife', $roleSet, true);
        $hasChild = collect(['son', 'daughter', 'child'])->intersect($roleSet)->isNotEmpty();
        $allFriends = collect($roleSet)->every(fn ($role) => $role === 'friend');

        if ($hasHusband && $hasWife && $roles->count() === 2 && ! $hasChild) {
            return 'husband & wife';
        }

        if ($hasHusband || $hasWife || $hasChild) {
            return 'family';
        }

        if ($allFriends) {
            return 'friends';
        }

        return 'group';
    }
}
