<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSoftDeletedEmailReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_reuses_soft_deleted_email_for_admin_sales_and_operations(): void
    {
        Role::findOrCreate('customer', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');

        $userService = app(UserService::class);

        foreach (['admin', 'sales', 'operations'] as $role) {
            $email = sprintf('%s.reused@example.com', $role);

            $deletedUser = User::factory()->create([
                'name' => 'Soft Deleted '.$role,
                'email' => $email,
            ]);
            $deletedUser->assignRole('customer');
            $deletedUser->delete();

            $createdUser = $userService->store([
                'role' => $role,
                'name' => 'Reactivated '.ucfirst($role),
                'email' => $email,
                'contact' => '0123456789',
                'password' => 'secret123',
                'scope_ids' => [],
            ]);

            $this->assertSame($deletedUser->id, $createdUser->id);
            $this->assertNull($createdUser->deleted_at);
            $this->assertTrue($createdUser->hasRole($role));
            $this->assertFalse($createdUser->hasRole('customer'));
            $this->assertSame(1, User::withTrashed()->where('email', $email)->count());
        }
    }

    public function test_store_customer_reuses_soft_deleted_email_without_creating_duplicate_customer_row(): void
    {
        Role::findOrCreate('customer', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');

        $userService = app(UserService::class);

        $email = 'customer.reused@example.com';

        $deletedUser = User::factory()->create([
            'name' => 'Old Customer User',
            'email' => $email,
        ]);
        $deletedUser->assignRole('customer');

        $existingCustomer = Customer::create([
            'user_id' => $deletedUser->id,
            'customer_number' => 'CUST-OLD-0001',
            'address' => 'Old address',
        ]);

        $deletedUser->delete();

        $createdUser = $userService->store([
            'role' => 'customer',
            'name' => 'New Customer User',
            'email' => $email,
            'contact' => '0198765432',
            'password' => 'secret123',
            'customer_number' => 'CUST-NEW-0001',
            'address' => 'New address',
            'scope_ids' => [],
        ]);

        $this->assertSame($deletedUser->id, $createdUser->id);
        $this->assertNull($createdUser->deleted_at);
        $this->assertTrue($createdUser->hasRole('customer'));
        $this->assertSame(1, User::withTrashed()->where('email', $email)->count());
        $this->assertSame(1, Customer::where('user_id', $createdUser->id)->count());

        $updatedCustomer = Customer::where('user_id', $createdUser->id)->firstOrFail();
        $this->assertSame($existingCustomer->id, $updatedCustomer->id);
        $this->assertSame('New address', $updatedCustomer->address);
    }
}
