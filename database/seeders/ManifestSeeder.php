<?php

namespace Database\Seeders;

use App\Helpers\NumberGenerator;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\Package;
use App\Models\QuotationItem;
use App\Models\ReceiptAllocation;
use Illuminate\Database\Seeder;

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

                $nextSn = ($manifest->travelers()->max('sn') ?? 0) + 1;

                $totalCost = (float) QuotationItem::query()
                    ->where('customer_confirmation_member_id', $member->id)
                    ->where('is_header', false)
                    ->get()
                    ->sum(function (QuotationItem $item): float {
                        return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                    });

                $totalPaid = (float) ReceiptAllocation::query()
                    ->where('customer_confirmation_member_id', $member->id)
                    ->sum('allocated_amount');

                $manifest->travelers()->create([
                    'sn' => $nextSn,
                    'customer_id' => $member->customer_id,
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => $member->customer?->user?->name ?? 'Member #'.$member->id,
                    'total_cost' => round($totalCost, 2),
                    'total_paid' => round($totalPaid, 2),
                    'outstanding_amount' => round(max(0, $totalCost - $totalPaid), 2),
                    'status' => 'assigned',
                ]);
            }
        }

        $this->command->info('Manifests seeded successfully (with paid traveler assignments).');
    }
}
