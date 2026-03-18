<?php

namespace Tests\Feature;

use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Quotation;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EnquirySeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\QuotationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerConfirmationSeederDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_mixed_confirmation_status_and_non_leader_quotation_handlers(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PackageSeeder::class);
        $this->seed(EnquirySeeder::class);
        $this->seed(QuotationSeeder::class);

        $groups = CustomerConfirmation::query()
            ->with(['members', 'quotations', 'quotations.quotationItems'])
            ->get();

        $this->assertGreaterThanOrEqual(10, $groups->count());

        $hasAllDraftGroup = $groups->contains(function (CustomerConfirmation $group) {
            if ($group->members->isEmpty()) {
                return false;
            }

            return $group->members->every(fn (CustomerConfirmationMember $member) => $member->status === 'draft');
        });

        $hasPartiallyPaidMember = CustomerConfirmationMember::query()
            ->where('status', 'partially_paid')
            ->exists();

        $hasFullyConfirmedGroup = $groups->contains(function (CustomerConfirmation $group) {
            $activeMembers = $group->members->where('status', '!=', 'cancelled');

            if ($activeMembers->isEmpty()) {
                return false;
            }

            return $activeMembers->every(fn (CustomerConfirmationMember $member) => $member->status === 'confirmed');
        });

        $hasNonLeaderHandledQuotation = Quotation::query()
            ->with('customerConfirmation.members')
            ->whereNotNull('customer_confirmation_id')
            ->get()
            ->contains(function (Quotation $quotation) {
                $leader = $quotation->customerConfirmation?->members->firstWhere('is_leader', true);

                if (! $leader) {
                    return false;
                }

                return (int) $leader->customer_id !== (int) $quotation->customer_id;
            });

        $this->assertTrue($hasAllDraftGroup);
        $this->assertTrue($hasPartiallyPaidMember);
        $this->assertTrue($hasFullyConfirmedGroup);
        $this->assertTrue($hasNonLeaderHandledQuotation);
    }
}
