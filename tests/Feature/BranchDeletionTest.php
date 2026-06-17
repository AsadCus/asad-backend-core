<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Country;
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
        $branchToDelete = Branch::query()->create([
            'name' => 'Branch Delete Test',
            'country_id' => $country->id,
        ]);
        $branchToKeep = Branch::query()->create([
            'name' => 'Branch Keep Test',
            'country_id' => $country->id,
        ]);

        $adminUser = User::factory()->create();
        $admin = Admin::query()->create([
            'user_id' => $adminUser->id,
            'branch_id' => $branchToDelete->id,
            'branch_ids' => [$branchToDelete->id, $branchToKeep->id],
            'country_id' => $country->id,
            'country_ids' => [$country->id],
        ]);

        $deleted = app(BranchService::class)->delete((string) $branchToDelete->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('branches', ['id' => $branchToDelete->id]);

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'branch_id' => null,
        ]);

        $this->assertSame([$branchToKeep->id], Admin::query()->findOrFail($admin->id)->branch_ids);
    }
}
