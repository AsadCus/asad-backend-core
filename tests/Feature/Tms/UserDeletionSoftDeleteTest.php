<?php

namespace Tests\Feature\Tms;

use App\Models\Customer;
use App\Models\User;
use App\Services\UserService;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class UserDeletionSoftDeleteTest extends TestCase
{
    public function test_deleting_customer_user_soft_deletes_user_and_keeps_customer_record(): void
    {
        Role::findOrCreate('customer', 'web');

        $user = User::factory()->create([
            'email' => 'soft-delete-customer@example.com',
        ]);
        $user->assignRole('customer');

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-SOFT-DELETE-001',
        ]);

        $result = app(UserService::class)->delete($user->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_deleting_non_customer_user_soft_deletes_user_and_keeps_related_customer_data(): void
    {
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('customer', 'web');

        $salesUser = User::factory()->create([
            'email' => 'soft-delete-sales@example.com',
        ]);
        $salesUser->assignRole('sales');

        $customerUser = User::factory()->create([
            'email' => 'assigned-customer@example.com',
        ]);
        $customerUser->assignRole('customer');

        $assignedCustomer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-SOFT-DELETE-002',
        ]);

        $result = app(UserService::class)->delete($salesUser->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('users', ['id' => $salesUser->id]);
        $this->assertDatabaseHas('customers', [
            'id' => $assignedCustomer->id,
            'user_id' => $customerUser->id,
        ]);
    }
}
