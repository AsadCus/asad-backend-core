<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Operation;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_user_destroy_soft_deletes_user_and_preserves_role_models(): void
    {
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');
        Role::findOrCreate('customer', 'web');

        $actor = User::factory()->create();
        $actor->assignRole('superadmin');

        $this->actingAs($actor);

        $cases = [
            [
                'role' => 'superadmin',
                'route' => 'master.user.superadmin.destroy',
                'param' => 'superadmin',
                'redirect' => 'master.user.superadmin.index',
                'table' => 'admins',
                'createScope' => static fn (User $user): int => (int) Admin::create([
                    'user_id' => $user->id,
                ])->id,
            ],
            [
                'role' => 'sales',
                'route' => 'master.user.sales.destroy',
                'param' => 'sale',
                'redirect' => 'master.user.sales.index',
                'table' => 'sales',
                'createScope' => static fn (User $user): int => (int) Sales::create([
                    'user_id' => $user->id,
                ])->id,
            ],
            [
                'role' => 'operations',
                'route' => 'master.user.operations.destroy',
                'param' => 'operation',
                'redirect' => 'master.user.operations.index',
                'table' => 'operations',
                'createScope' => static fn (User $user): int => (int) Operation::create([
                    'user_id' => $user->id,
                ])->id,
            ],
            [
                'role' => 'customer',
                'route' => 'master.user.customer.destroy',
                'param' => 'customer',
                'redirect' => 'master.user.customer.index',
                'table' => 'customers',
                'createScope' => static fn (User $user): int => (int) Customer::create([
                    'user_id' => $user->id,
                    'customer_number' => 'CUST-DEL-'.$user->id,
                ])->id,
            ],
        ];

        foreach ($cases as $case) {
            $targetUser = User::factory()->create();
            $targetUser->assignRole($case['role']);

            $scopeId = $case['createScope']($targetUser);

            $this->delete(route($case['route'], [$case['param'] => $targetUser->id]))
                ->assertRedirect(route($case['redirect']));

            $this->assertSoftDeleted('users', [
                'id' => $targetUser->id,
            ]);

            $this->assertDatabaseHas($case['table'], [
                'id' => $scopeId,
                'user_id' => $targetUser->id,
            ]);
        }
    }
}
