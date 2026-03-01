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
        if (Quotation::query()->exists()) {
            $this->command->info('Quotations already seeded, skipping...');

            return;
        }

        $groups = CustomerConfirmation::query()
            ->with(['members.customer.user', 'members.quotationItems', 'package'])
            ->whereNotNull('package_id')
            ->orderBy('id')
            ->take(8)
            ->get();

        if ($groups->isEmpty()) {
            $this->command->warn('No customer confirmations found for quotation seeding.');

            return;
        }

        foreach ($groups as $index => $group) {
            $activeMembers = $group->members
                ->filter(fn (CustomerConfirmationMember $member) => $member->status !== 'cancelled')
                ->values();

            if ($activeMembers->isEmpty()) {
                continue;
            }

            $shouldSeedPartially = $index % 2 === 0 && $activeMembers->count() > 1;
            $membersToQuote = $shouldSeedPartially
                ? $activeMembers->slice(0, $activeMembers->count() - 1)->values()
                : $activeMembers;

            if ($membersToQuote->isEmpty()) {
                continue;
            }

            $payerMember = $membersToQuote->firstWhere('is_leader', true) ?? $membersToQuote->first();

            if (! $payerMember?->customer_id) {
                continue;
            }

            $quotation = Quotation::query()->create([
                'customer_id' => $payerMember->customer_id,
                'customer_confirmation_id' => $group->id,
                'quotation_date' => Carbon::now()->subDays(25 - min($index, 20))->toDateString(),
                'expiry_date' => Carbon::now()->addDays(7 + min($index, 15))->toDateString(),
                'payment_plan' => 'full',
                'payment_method' => 'transfer',
                'description' => 'Seeded quotation from customer confirmation workflow',
                'status' => 'accepted',
            ]);

            foreach ($membersToQuote as $memberIndex => $member) {
                $amount = $this->resolvePackageAmount($group, $member);

                QuotationItem::query()->create([
                    'quotation_id' => $quotation->id,
                    'customer_confirmation_member_id' => $member->id,
                    'description' => ($member->customer?->user?->name ?? 'Member').' - '.ucfirst((string) $member->sharing_plan).' sharing',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => $amount,
                    'sort_order' => $memberIndex + 1,
                ]);

                if (in_array($member->status, ['draft', 'confirmed'], true)) {
                    $member->update(['status' => 'pending_payment']);
                }
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
