<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Indonesia Maid Agency',
                'email' => 'supplier1@example.com',
                'address' => 'Jakarta, Indonesia',
            ],
            [
                'name' => 'Philippines Domestic Helper Agency',
                'email' => 'supplier2@example.com',
                'address' => 'Manila, Philippines',
            ],
            [
                'name' => 'Myanmar Worker Agency',
                'email' => 'supplier3@example.com',
                'address' => 'Yangon, Myanmar',
            ],
        ];

        foreach ($suppliers as $supplierData) {
            // Create user first
            $user = User::create([
                'name' => $supplierData['name'],
                'email' => $supplierData['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            // Assign supplier role if exists
            if ($user->roles()->exists()) {
                $supplierRole = Role::where('name', 'supplier')->first();
                if ($supplierRole) {
                    $user->assignRole($supplierRole);
                }
            }

            // Create supplier
            Supplier::create([
                'user_id' => $user->id,
                'name' => $supplierData['name'],
                'address' => $supplierData['address'],
            ]);
        }
    }
}
