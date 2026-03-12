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
                    'status' => 'draft',
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

            foreach ($paidMembers as $member) {
                $alreadyLinked = $manifest->travelers()
                    ->where('customer_confirmation_member_id', $member->id)
                    ->exists();

                if ($alreadyLinked) {
                    continue;
                }

                $manifest->travelers()->create([
                    'customer_confirmation_member_id' => $member->id,
                ]);
            }

            $manifest->load(['travelers', 'rooms.roomMembers']);

            foreach ($manifest->rooms as $room) {
                $room->roomMembers()->delete();
            }
            $manifest->rooms()->delete();

            $travelersByMember = $manifest->travelers
                ->filter(fn ($traveler) => ! empty($traveler->customer_confirmation_member_id))
                ->keyBy('customer_confirmation_member_id');

            $defaultLocation = optional($package->accommodations->first())->location ?? 'mekkah';
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
                        'location' => $defaultLocation,
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
                        $traveler = $travelersByMember->get($member->id);

                        if (! $traveler) {
                            continue;
                        }

                        $room->roomMembers()->create([
                            'manifest_traveler_id' => $traveler->id,
                            'sort_order' => $index + 1,
                            'remarks' => null,
                        ]);
                    }

                    $roomCounter++;
                }
            }
        }

        $this->command->info('Manifests seeded successfully (with paid traveler assignments).');
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
}
