<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Sales;
use App\Models\User;
use App\Services\BranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_branch_nulls_related_branch_ids_and_deletes_branch(): void
    {
        $country = Country::factory()->create();
        $branch = Branch::query()->create([
            'name' => 'Branch Delete Test',
            'country_id' => $country->id,
        ]);

        $opsUser = User::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $customerUser = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-BRANCH-DELETE',
            'branch_id' => $branch->id,
        ]);

        $salesUser = User::factory()->create([
            'branch_id' => $branch->id,
        ]);
        $sales = Sales::query()->create([
            'user_id' => $salesUser->id,
            'branch_id' => $branch->id,
        ]);

        $deleted = app(BranchService::class)->delete((string) $branch->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);

        $this->assertDatabaseHas('users', [
            'id' => $opsUser->id,
            'branch_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $salesUser->id,
            'branch_id' => null,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'branch_id' => null,
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $sales->id,
            'branch_id' => null,
        ]);
    }
}
