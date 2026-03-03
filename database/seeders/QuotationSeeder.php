<?php

namespace Database\Seeders;

use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class QuotationSeeder extends Seeder
{
    public function run(): void
    {
        $groups = CustomerConfirmation::query()
            ->with(['members.customer.user', 'members.quotationItems', 'package'])
            ->whereNotNull('package_id')
            ->orderBy('id')
            ->get();

        if ($groups->isEmpty()) {
            $this->command->warn('No customer confirmations found for quotation seeding.');

            return;
        }

        foreach ($groups as $group) {
            $eligibleMembers = $group->members
                ->filter(fn (CustomerConfirmationMember $member) => in_array($member->status, ['pending_payment', 'partially_paid', 'confirmed'], true))
                ->filter(fn (CustomerConfirmationMember $member) => $member->quotationItems->isEmpty())
                ->values();

            if ($eligibleMembers->isEmpty()) {
                continue;
            }

            $payerMember = $group->members->firstWhere('is_leader', true) ?? $eligibleMembers->first();

            if (! $payerMember?->customer_id) {
                continue;
            }

            $remainingMembers = $eligibleMembers->values();
            $quotationIndex = 0;

            if ($payerMember && $remainingMembers->contains('id', $payerMember->id)) {
                $leaderCoveredMembers = $remainingMembers->take(min(2, $remainingMembers->count()))->values();

                if ($leaderCoveredMembers->isNotEmpty()) {
                    $quotation = Quotation::query()->create([
                        'customer_id' => $payerMember->customer_id,
                        'customer_confirmation_id' => $group->id,
                        'quotation_date' => Carbon::now()->subDays(fake()->numberBetween(5, 30))->toDateString(),
                        'expiry_date' => Carbon::now()->addDays(fake()->numberBetween(7, 21))->toDateString(),
                        'payment_plan' => (($group->id + $quotationIndex) % 2 === 0) ? 'installment' : 'full',
                        'payment_method' => 'transfer',
                        'description' => 'Seeded quotation from payment workflow',
                        'status' => 'accepted',
                    ]);

                    foreach ($leaderCoveredMembers->values() as $itemIndex => $member) {
                        $amount = $this->resolvePackageAmount($group, $member);

                        QuotationItem::query()->create([
                            'quotation_id' => $quotation->id,
                            'customer_confirmation_member_id' => $member->id,
                            'description' => ($member->customer?->user?->name ?? 'Member').' - '.ucfirst((string) $member->sharing_plan).' sharing',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => $amount,
                            'sort_order' => $itemIndex + 1,
                        ]);
                    }

                    $quotationIndex++;
                    $remainingMemberIds = $leaderCoveredMembers->pluck('id')->all();
                    $remainingMembers = $remainingMembers
                        ->reject(fn (CustomerConfirmationMember $member) => in_array($member->id, $remainingMemberIds, true))
                        ->values();
                }
            }

            foreach ($remainingMembers as $member) {
                if (! $member->customer_id) {
                    continue;
                }

                $quotation = Quotation::query()->create([
                    'customer_id' => $member->customer_id,
                    'customer_confirmation_id' => $group->id,
                    'quotation_date' => Carbon::now()->subDays(fake()->numberBetween(5, 30))->toDateString(),
                    'expiry_date' => Carbon::now()->addDays(fake()->numberBetween(7, 21))->toDateString(),
                    'payment_plan' => (($group->id + $quotationIndex) % 2 === 0) ? 'installment' : 'full',
                    'payment_method' => 'transfer',
                    'description' => 'Seeded quotation from payment workflow',
                    'status' => 'accepted',
                ]);

                $amount = $this->resolvePackageAmount($group, $member);

                QuotationItem::query()->create([
                    'quotation_id' => $quotation->id,
                    'customer_confirmation_member_id' => $member->id,
                    'description' => ($member->customer?->user?->name ?? 'Member').' - '.ucfirst((string) $member->sharing_plan).' sharing',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => $amount,
                    'sort_order' => 1,
                ]);

                $quotationIndex++;
            }
        }

        $this->command->info('Quotations seeded successfully.');
    }

    private function resolvePackageAmount(CustomerConfirmation $group, CustomerConfirmationMember $member): float
    {
        if (! $group->package || ! $member->sharing_plan) {
            return 0;
        }

        return match ($member->sharing_plan) {
            'single' => (float) ($group->package->price_single ?? 0),
            'double' => (float) ($group->package->price_double ?? 0),
            'triple' => (float) ($group->package->price_triple ?? 0),
            'quad' => (float) ($group->package->price_quad ?? 0),
            default => 0,
        };
    }
}
