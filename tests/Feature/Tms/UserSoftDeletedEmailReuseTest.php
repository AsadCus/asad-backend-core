<?php

namespace Tests\Feature\Tms;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\User;
use App\Services\UserService;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class UserSoftDeletedEmailReuseTest extends TestCase
{
    /**
     * @return array<int, array{0:string}>
     */
    public static function scopedRoleProvider(): array
    {
        return [
            ['superadmin'],
            ['admin'],
            ['sales'],
            ['operations'],
        ];
    }

    public function test_store_reuses_soft_deleted_email_for_superadmin_admin_sales_and_operations(): void
    {
        Role::findOrCreate('customer', 'web');
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');

        $userService = app(UserService::class);

        foreach (['superadmin', 'admin', 'sales', 'operations'] as $role) {
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
        Role::findOrCreate('superadmin', 'web');
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

    #[DataProvider('scopedRoleProvider')]
    public function test_store_initializes_selected_country_ids_from_country_scope(string $role): void
    {
        config(['data_scope.mode' => 'country']);

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');

        $countryA = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);
        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $createdUser = app(UserService::class)->store([
            'role' => $role,
            'name' => 'Scoped '.ucfirst($role),
            'email' => sprintf('scoped-%s-country@example.com', $role),
            'contact' => '0123456789',
            'password' => 'secret123',
            'scope_ids' => [$countryB->id, $countryA->id],
        ]);

        $createdUser->refresh();

        $this->assertSame([$countryB->id, $countryA->id], $createdUser->selected_country_ids);
    }

    #[DataProvider('scopedRoleProvider')]
    public function test_store_initializes_selected_country_ids_from_branch_scope(string $role): void
    {
        config(['data_scope.mode' => 'branch']);

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');

        $countryA = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);
        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $branchA = Branch::create([
            'country_id' => $countryA->id,
            'name' => 'SG Branch',
            'adjective' => 'Singapore',
        ]);

        $branchB = Branch::create([
            'country_id' => $countryB->id,
            'name' => 'Jakarta Branch',
            'adjective' => 'Jakarta',
        ]);

        $createdUser = app(UserService::class)->store([
            'role' => $role,
            'name' => 'Scoped '.ucfirst($role),
            'email' => sprintf('scoped-%s-branch@example.com', $role),
            'contact' => '0123456789',
            'password' => 'secret123',
            'scope_ids' => [$branchB->id, $branchA->id],
        ]);

        $createdUser->refresh();

        $this->assertEqualsCanonicalizing([$countryA->id, $countryB->id], $createdUser->selected_country_ids);
    }
}
