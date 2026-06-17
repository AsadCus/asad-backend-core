<?php

namespace Tests\Feature\Tms;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Enquiry;
use App\Models\Operation;
use App\Models\Sales;
use App\Models\User;
use App\Services\BranchService;
use Tests\TmsTestCase as TestCase;

class BranchDeletionTest extends TestCase
{
    public function test_delete_branch_nulls_related_branch_ids_and_deletes_branch(): void
    {
        $country = Country::factory()->create();
        $branchToDelete = Branch::query()->create([
            'name' => 'Branch Delete Test',
            'country_id' => $country->id,
        ]);
        $branchToKeep = Branch::query()->create([
            'name' => 'Branch Keep Test',
            'country_id' => $country->id,
        ]);

        $opsUser = User::factory()->create();
        $operation = Operation::query()->create([
            'user_id' => $opsUser->id,
            'branch_id' => $branchToDelete->id,
            'branch_ids' => [$branchToDelete->id, $branchToKeep->id],
            'country_id' => $country->id,
            'country_ids' => [$country->id],
        ]);

        $adminUser = User::factory()->create();
        $admin = Admin::query()->create([
            'user_id' => $adminUser->id,
            'branch_id' => $branchToDelete->id,
            'branch_ids' => [$branchToDelete->id, $branchToKeep->id],
            'country_id' => $country->id,
            'country_ids' => [$country->id],
        ]);

        $salesUser = User::factory()->create();
        $sales = Sales::query()->create([
            'user_id' => $salesUser->id,
            'branch_id' => $branchToDelete->id,
            'branch_ids' => [$branchToDelete->id, $branchToKeep->id],
            'country_id' => $country->id,
            'country_ids' => [$country->id],
        ]);

        $enquiry = Enquiry::query()->create([
            'type' => 'general',
            'enquiry_number' => 'ENQ-BRANCH-DELETE',
            'status' => 'new_lead',
            'name' => 'Branch Scope Enquiry',
            'contact_number' => '0123456789',
            'email' => 'branch-scope-enquiry@example.com',
            'created_by' => $salesUser->id,
            'branch_id' => $branchToDelete->id,
            'country_id' => $country->id,
        ]);

        $deleted = app(BranchService::class)->delete((string) $branchToDelete->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('branches', ['id' => $branchToDelete->id]);

        $this->assertDatabaseHas('sales', [
            'id' => $sales->id,
            'branch_id' => null,
        ]);

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'branch_id' => null,
        ]);

        $this->assertDatabaseHas('operations', [
            'id' => $operation->id,
            'branch_id' => null,
        ]);

        $this->assertDatabaseHas('enquiries', [
            'id' => $enquiry->id,
            'branch_id' => null,
        ]);

        $this->assertSame([$branchToKeep->id], Admin::query()->findOrFail($admin->id)->branch_ids);
        $this->assertSame([$branchToKeep->id], Operation::query()->findOrFail($operation->id)->branch_ids);
        $this->assertSame([$branchToKeep->id], Sales::query()->findOrFail($sales->id)->branch_ids);
    }
}
