<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Sales;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ProductionUserSeeder extends Seeder
{
    /**
     * Seed production users from SQL data.
     */
    public function run(): void
    {
        // Admin Users
        $adminUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'contact' => '+6500000000',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Asad',
                'email' => 'asad@example.com',
                'contact' => '+6400000000000',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        ];

        // Sales Users
        $salesUsers = [
            [
                'name' => 'Sales User',
                'email' => 'sales@example.com',
                'contact' => '+6400000000000',
                'password' => Hash::make('password'),
                'role' => 'sales',
                'branch_id' => 1,
            ],
        ];

        // Customer Users
        $customerUsers = [
            [
                'name' => 'Customer User',
                'email' => 'customer@example.com',
                'contact' => '+6400000000000',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'customer_number' => 'UC-2026-0017',
                'branch_id' => 1,
            ],
        ];

        // Supplier Users
        $supplierUsers = [
            [
                'name' => 'Supplier User',
                'email' => 'supplier@example.com',
                'contact' => '+6400000000000',
                'password' => Hash::make('password'),
                'role' => 'supplier',
                'supplier_name' => 'Supplier Company',
                'address' => 'Supplier Company Address',
            ],
        ];

        // Create Admin Users
        foreach ($adminUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'contact' => $userData['contact'],
                'password' => $userData['password'],
                'email_verified_at' => now(),
            ]);
            $user->assignRole(Role::findByName('admin'));
        }

        // Create Sales Users
        foreach ($salesUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'contact' => $userData['contact'],
                'password' => $userData['password'],
                'email_verified_at' => now(),
            ]);
            $user->assignRole(Role::findByName('sales'));

            // Create Sales record
            Sales::create([
                'user_id' => $user->id,
                'branch_id' => $userData['branch_id'],
            ]);
        }

        // Create Customer Users
        foreach ($customerUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'contact' => $userData['contact'],
                'password' => $userData['password'],
                'email_verified_at' => now(),
            ]);
            $user->assignRole(Role::findByName('customer'));

            // Create Customer record
            Customer::create([
                'user_id' => $user->id,
                'customer_number' => $userData['customer_number'],
                'nric_number' => $userData['nric_number'] ?? null,
                'address' => $userData['address'] ?? null,
                'age_preferences' => '["18-25","26-35","36-45","45+"]',
                'country_preferences' => '["Indonesia"]',
                'experience_preferences' => '["0-1","2-3","4-5","5+"]',
                'branch_id' => $userData['branch_id'],
                'handled_by' => 1, // First admin user
            ]);
        }

        // Create Supplier Users
        foreach ($supplierUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'contact' => $userData['contact'],
                'password' => $userData['password'],
                'email_verified_at' => now(),
            ]);
            $user->assignRole(Role::findByName('supplier'));

            // Create Supplier record
            Supplier::create([
                'user_id' => $user->id,
                'name' => $userData['supplier_name'],
                'address' => $userData['address'],
            ]);
        }

        $this->command->info('Production users created successfully!');
    }
}
