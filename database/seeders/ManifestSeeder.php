<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ManifestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Manifest::count() > 0) {
            $this->command->info('Manifests already seeded, skipping...');

            return;
        }

        $package1 = Package::where('name', 'Umrah Economy 14 Days')->first();
        $package2 = Package::where('name', 'Umrah Premium 10 Days')->first();

        if (! $package1 || ! $package2) {
            $this->command->warn('Packages not found. Please run PackageSeeder first.');

            return;
        }

        $confirmation1 = $this->ensureConfirmationForPackage($package1, 5, 'economy');
        $confirmation2 = $this->ensureConfirmationForPackage($package2, 2, 'premium');

        $manifest1 = Manifest::create([
            'package_id' => $package1->id,
            'reference_number' => 'MNF-2026-001',
            'company_address' => '123 Travel House, London, UK',
            'company_phone' => '+44 20 1234 5678',
            'departure_date' => '2026-01-15',
            'return_date' => '2026-01-29',
            'duration' => '14 Days / 13 Nights',
            'makkah_hotel' => 'Al Shohada Hotel',
            'makkah_check_in' => '2026-01-16',
            'makkah_check_out' => '2026-01-23',
            'madinah_hotel' => 'Dar Al Taqwa Hotel',
            'madinah_check_in' => '2026-01-23',
            'madinah_check_out' => '2026-01-28',
            'flight_details' => [
                ['type' => 'Departure', 'airline' => 'Saudi Airlines', 'flight_no' => 'SV-116', 'date' => '2026-01-15', 'route' => 'LHR → JED'],
                ['type' => 'Return', 'airline' => 'Saudi Airlines', 'flight_no' => 'SV-117', 'date' => '2026-01-29', 'route' => 'MED → LHR'],
            ],
            'notes' => 'Seeded from customer confirmation members.',
            'first_meal' => 'Dinner',
            'last_meal' => 'Breakfast',
            'status' => 'confirmed',
        ]);

        $manifest1Travelers = $this->createTravelersFromConfirmation(
            $manifest1,
            $confirmation1,
            [
                ['room_no' => '101', 'room_type' => 'QUAD', 'bed_type' => 'SINGLE'],
                ['room_no' => '101', 'room_type' => 'QUAD', 'bed_type' => 'SINGLE'],
                ['room_no' => '101', 'room_type' => 'QUAD', 'bed_type' => 'SINGLE'],
                ['room_no' => '102', 'room_type' => 'DOUBLE', 'bed_type' => 'KING'],
                ['room_no' => '102', 'room_type' => 'DOUBLE', 'bed_type' => 'KING'],
            ],
        );

        $manifest1Rooms = $manifest1->rooms()->createMany([
            ['location' => 'Makkah', 'room_number' => '101', 'room_type' => 'QUAD', 'bed_type' => 'SINGLE', 'capacity' => 4, 'sharing_plan' => 'quad', 'status' => 'filled', 'room_label' => 'Makkah-101'],
            ['location' => 'Makkah', 'room_number' => '102', 'room_type' => 'DOUBLE', 'bed_type' => 'KING', 'capacity' => 2, 'sharing_plan' => 'double', 'status' => 'filled', 'room_label' => 'Makkah-102'],
            ['location' => 'Madinah', 'room_number' => '201', 'room_type' => 'DOUBLE', 'bed_type' => 'KING', 'capacity' => 2, 'sharing_plan' => 'double', 'status' => 'pending', 'room_label' => 'Madinah-201'],
            ['location' => 'Madinah', 'room_number' => '202', 'room_type' => 'QUAD', 'bed_type' => 'SINGLE', 'capacity' => 4, 'sharing_plan' => 'quad', 'status' => 'pending', 'room_label' => 'Madinah-202'],
        ]);

        $travelersByRoomNo = $manifest1Travelers->groupBy('room_no');
        foreach ($manifest1Rooms as $room) {
            foreach ($travelersByRoomNo->get($room->room_number, collect()) as $roomTraveler) {
                $room->roomMembers()->create([
                    'manifest_traveler_id' => $roomTraveler->id,
                    'role_in_room' => strtolower((string) ($roomTraveler->relationship ?? 'participant')),
                ]);
            }
        }

        $manifest1TravelerByIndex = $manifest1Travelers->values();

        $manifest1->accommodationAssignments()->createMany([
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(0)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(0)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(0)?->customer_confirmation_member_id,
                'accommodation_key' => 'makkah',
                'sort_order' => 1,
                'sharing_group_key' => 'room-101',
                'room_no' => '101',
                'room_type' => 'QUAD',
                'bed_type' => 'SINGLE',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(1)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(1)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(1)?->customer_confirmation_member_id,
                'accommodation_key' => 'makkah',
                'sort_order' => 2,
                'sharing_group_key' => 'room-101',
                'room_no' => '101',
                'room_type' => 'QUAD',
                'bed_type' => 'SINGLE',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(2)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(2)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(2)?->customer_confirmation_member_id,
                'accommodation_key' => 'makkah',
                'sort_order' => 3,
                'sharing_group_key' => 'room-101',
                'room_no' => '101',
                'room_type' => 'QUAD',
                'bed_type' => 'SINGLE',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(3)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(3)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(3)?->customer_confirmation_member_id,
                'accommodation_key' => 'makkah',
                'sort_order' => 4,
                'sharing_group_key' => 'room-102',
                'room_no' => '102',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(4)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(4)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(4)?->customer_confirmation_member_id,
                'accommodation_key' => 'makkah',
                'sort_order' => 5,
                'sharing_group_key' => 'room-102',
                'room_no' => '102',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(3)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(3)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(3)?->customer_confirmation_member_id,
                'accommodation_key' => 'madinah',
                'sort_order' => 1,
                'sharing_group_key' => 'room-201',
                'room_no' => '201',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(4)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(4)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(4)?->customer_confirmation_member_id,
                'accommodation_key' => 'madinah',
                'sort_order' => 2,
                'sharing_group_key' => 'room-201',
                'room_no' => '201',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(0)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(0)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(0)?->customer_confirmation_member_id,
                'accommodation_key' => 'madinah',
                'sort_order' => 3,
                'sharing_group_key' => 'room-202',
                'room_no' => '202',
                'room_type' => 'QUAD',
                'bed_type' => 'SINGLE',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(1)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(1)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(1)?->customer_confirmation_member_id,
                'accommodation_key' => 'madinah',
                'sort_order' => 4,
                'sharing_group_key' => 'room-202',
                'room_no' => '202',
                'room_type' => 'QUAD',
                'bed_type' => 'SINGLE',
                'meal' => 'Breakfast',
            ],
            [
                'manifest_traveler_id' => $manifest1TravelerByIndex->get(2)?->id,
                'customer_id' => $manifest1TravelerByIndex->get(2)?->customer_id,
                'customer_confirmation_member_id' => $manifest1TravelerByIndex->get(2)?->customer_confirmation_member_id,
                'accommodation_key' => 'madinah',
                'sort_order' => 5,
                'sharing_group_key' => 'room-202',
                'room_no' => '202',
                'room_type' => 'QUAD',
                'bed_type' => 'SINGLE',
                'meal' => 'Breakfast',
            ],
        ]);

        $manifest1->payments()->createMany($manifest1Travelers->map(function ($traveler) {
            return [
                'manifest_traveler_id' => $traveler->id,
                'traveler_name' => $traveler->name_as_per_passport,
                'description' => 'Seeded payment from confirmation flow',
                'amount' => 2500.00,
                'paid_amount' => 1800.00,
                'outstanding_amount' => 700.00,
                'payment_date' => '2025-12-15',
                'status' => 'partial',
            ];
        })->toArray());

        $manifest1FlightDetails = is_array($manifest1->flight_details) ? $manifest1->flight_details : [];
        $manifest1FlightDetails['ui_room_lists'] = $manifest1->accommodationAssignments()
            ->orderBy('accommodation_key')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('accommodation_key')
            ->map(function ($assignments) {
                return $assignments->map(function ($assignment) {
                    return [
                        'manifest_traveler_id' => $assignment->manifest_traveler_id,
                        'customer_id' => $assignment->customer_id,
                        'customer_confirmation_member_id' => $assignment->customer_confirmation_member_id,
                        'sort_order' => $assignment->sort_order,
                        'sharing_group_key' => $assignment->sharing_group_key,
                        'room_no' => $assignment->room_no,
                        'room_type' => $assignment->room_type,
                        'bed_type' => $assignment->bed_type,
                        'meal' => $assignment->meal,
                        'remarks' => $assignment->remarks,
                    ];
                })->values()->all();
            })
            ->toArray();
        $manifest1FlightDetails['ui_room_move_modes'] = [
            'makkah' => 'individual',
            'madinah' => 'individual',
        ];
        $manifest1->update(['flight_details' => $manifest1FlightDetails]);

        $manifest2 = Manifest::create([
            'package_id' => $package2->id,
            'reference_number' => 'MNF-2026-002',
            'company_address' => '123 Travel House, London, UK',
            'company_phone' => '+44 20 1234 5678',
            'departure_date' => '2026-02-10',
            'return_date' => '2026-02-20',
            'duration' => '10 Days / 9 Nights',
            'makkah_hotel' => 'Fairmont Makkah Clock Royal Tower',
            'makkah_check_in' => '2026-02-11',
            'makkah_check_out' => '2026-02-16',
            'madinah_hotel' => 'The Oberoi Madinah',
            'madinah_check_in' => '2026-02-16',
            'madinah_check_out' => '2026-02-19',
            'flight_details' => [
                ['type' => 'Departure', 'airline' => 'British Airways', 'flight_no' => 'BA-263', 'date' => '2026-02-10', 'route' => 'LHR → JED'],
                ['type' => 'Return', 'airline' => 'British Airways', 'flight_no' => 'BA-264', 'date' => '2026-02-20', 'route' => 'MED → LHR'],
            ],
            'notes' => 'Premium confirmation travelers seeded from customer confirmation flow.',
            'first_meal' => 'Dinner',
            'last_meal' => 'Breakfast',
            'status' => 'draft',
        ]);

        $manifest2Travelers = $this->createTravelersFromConfirmation(
            $manifest2,
            $confirmation2,
            [
                ['room_no' => '501', 'room_type' => 'DOUBLE', 'bed_type' => 'KING'],
                ['room_no' => '501', 'room_type' => 'DOUBLE', 'bed_type' => 'KING'],
            ],
        );

        $manifest2Rooms = $manifest2->rooms()->createMany([
            ['location' => 'Makkah', 'room_number' => '501', 'room_type' => 'DOUBLE', 'bed_type' => 'KING', 'capacity' => 2, 'sharing_plan' => 'double', 'status' => 'filled', 'room_label' => 'Makkah-501'],
            ['location' => 'Madinah', 'room_number' => '601', 'room_type' => 'DOUBLE', 'bed_type' => 'KING', 'capacity' => 2, 'sharing_plan' => 'double', 'status' => 'pending', 'room_label' => 'Madinah-601'],
        ]);

        foreach ($manifest2Travelers as $traveler) {
            $manifest2Rooms->first()?->roomMembers()->create([
                'manifest_traveler_id' => $traveler->id,
                'role_in_room' => strtolower((string) ($traveler->relationship ?? 'participant')),
            ]);
        }

        $manifest2TravelerByIndex = $manifest2Travelers->values();

        $manifest2->accommodationAssignments()->createMany([
            [
                'manifest_traveler_id' => $manifest2TravelerByIndex->get(0)?->id,
                'customer_id' => $manifest2TravelerByIndex->get(0)?->customer_id,
                'customer_confirmation_member_id' => $manifest2TravelerByIndex->get(0)?->customer_confirmation_member_id,
                'accommodation_key' => 'makkah',
                'sort_order' => 1,
                'sharing_group_key' => 'room-501',
                'room_no' => '501',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Dinner',
            ],
            [
                'manifest_traveler_id' => $manifest2TravelerByIndex->get(1)?->id,
                'customer_id' => $manifest2TravelerByIndex->get(1)?->customer_id,
                'customer_confirmation_member_id' => $manifest2TravelerByIndex->get(1)?->customer_confirmation_member_id,
                'accommodation_key' => 'makkah',
                'sort_order' => 2,
                'sharing_group_key' => 'room-501',
                'room_no' => '501',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Dinner',
            ],
            [
                'manifest_traveler_id' => $manifest2TravelerByIndex->get(1)?->id,
                'customer_id' => $manifest2TravelerByIndex->get(1)?->customer_id,
                'customer_confirmation_member_id' => $manifest2TravelerByIndex->get(1)?->customer_confirmation_member_id,
                'accommodation_key' => 'madinah',
                'sort_order' => 1,
                'sharing_group_key' => 'room-601',
                'room_no' => '601',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Dinner',
            ],
            [
                'manifest_traveler_id' => $manifest2TravelerByIndex->get(0)?->id,
                'customer_id' => $manifest2TravelerByIndex->get(0)?->customer_id,
                'customer_confirmation_member_id' => $manifest2TravelerByIndex->get(0)?->customer_confirmation_member_id,
                'accommodation_key' => 'madinah',
                'sort_order' => 2,
                'sharing_group_key' => 'room-601',
                'room_no' => '601',
                'room_type' => 'DOUBLE',
                'bed_type' => 'KING',
                'meal' => 'Dinner',
            ],
        ]);

        $manifest2FlightDetails = is_array($manifest2->flight_details) ? $manifest2->flight_details : [];
        $manifest2FlightDetails['ui_room_lists'] = $manifest2->accommodationAssignments()
            ->orderBy('accommodation_key')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('accommodation_key')
            ->map(function ($assignments) {
                return $assignments->map(function ($assignment) {
                    return [
                        'manifest_traveler_id' => $assignment->manifest_traveler_id,
                        'customer_id' => $assignment->customer_id,
                        'customer_confirmation_member_id' => $assignment->customer_confirmation_member_id,
                        'sort_order' => $assignment->sort_order,
                        'sharing_group_key' => $assignment->sharing_group_key,
                        'room_no' => $assignment->room_no,
                        'room_type' => $assignment->room_type,
                        'bed_type' => $assignment->bed_type,
                        'meal' => $assignment->meal,
                        'remarks' => $assignment->remarks,
                    ];
                })->values()->all();
            })
            ->toArray();
        $manifest2FlightDetails['ui_room_move_modes'] = [
            'makkah' => 'individual',
            'madinah' => 'individual',
        ];
        $manifest2->update(['flight_details' => $manifest2FlightDetails]);

        $this->command->info('Manifest workflow records seeded successfully from customer confirmation flow.');
    }

    private function ensureConfirmationForPackage(Package $package, int $minimumMembers, string $seedKey): CustomerConfirmation
    {
        $confirmation = CustomerConfirmation::query()
            ->where('package_id', $package->id)
            ->with('members.customer.user')
            ->first();

        if ($confirmation && $confirmation->members->count() >= $minimumMembers) {
            return $confirmation;
        }

        $confirmation = CustomerConfirmation::create([
            'enquiry_id' => null,
            'created_by' => null,
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'date_of_application' => now()->subDays(7),
        ]);

        for ($index = 1; $index <= $minimumMembers; $index++) {
            $email = sprintf('%s-member-%d@example.com', $seedKey, $index);
            $name = sprintf('%s Member %d', Str::title($seedKey), $index);

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'contact' => '+60123456'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            );

            $customer = Customer::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'is_active' => true,
                    'passport_number' => strtoupper(substr($seedKey, 0, 3)).'P'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                    'passport_issue_date' => now()->subYears(2),
                    'passport_expiry_date' => now()->addYears(8),
                    'passport_place_of_issue' => 'KUALA LUMPUR',
                    'date_of_birth' => now()->subYears(25 + $index),
                    'nationality' => 'MALAYSIAN',
                ],
            );

            CustomerConfirmationMember::query()->create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $index === 1,
                'status' => $index === 1 ? 'confirmed' : 'pending_payment',
            ]);
        }

        return $confirmation->fresh(['members.customer.user']);
    }

    /**
     * @param  array<int, array{room_no: string, room_type: string, bed_type: string}>  $roomLayout
     */
    private function createTravelersFromConfirmation(Manifest $manifest, CustomerConfirmation $confirmation, array $roomLayout)
    {
        $members = $confirmation->members()
            ->with('customer.user')
            ->orderByDesc('is_leader')
            ->orderBy('id')
            ->limit(count($roomLayout))
            ->get();

        return $members->values()->map(function (CustomerConfirmationMember $member, int $index) use ($manifest, $roomLayout) {
            $layout = $roomLayout[$index] ?? ['room_no' => '', 'room_type' => 'DOUBLE', 'bed_type' => 'KING'];
            $customer = $member->customer;
            $user = $customer?->user;

            return $manifest->travelers()->create([
                'sn' => $index + 1,
                'customer_id' => $member->customer_id,
                'customer_confirmation_member_id' => $member->id,
                'name_as_per_passport' => $user?->name ?? 'Traveler '.($index + 1),
                'relationship' => $member->is_leader ? 'Self' : 'Family',
                'passport_no' => $customer?->passport_number,
                'room_no' => $layout['room_no'],
                'room_type' => $layout['room_type'],
                'bed_type' => $layout['bed_type'],
                'date_of_birth' => $customer?->date_of_birth,
                'age' => $customer?->date_of_birth?->age,
                'meal' => 'Breakfast',
                'total_cost' => 2500,
                'total_paid' => $member->status === 'confirmed' ? 2500 : 1200,
                'outstanding_amount' => $member->status === 'confirmed' ? 0 : 1300,
                'status' => 'assigned',
            ]);
        });
    }
}
