<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\GhostUser;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSalesUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::findByName('admin');
        $salesRole = Role::findByName('sales');

        $adminUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'contact' => '+6500000000',
            ],
            [
                'name' => 'Asad',
                'email' => 'asad@example.com',
                'contact' => '+6400000000000',
            ],
        ];

        foreach ($adminUsers as $adminData) {
            $adminUser = User::firstOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'contact' => $adminData['contact'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $adminUser->assignRole($adminRole);

            if ($adminData['email'] === 'asad@example.com') {
                GhostUser::firstOrCreate([
                    'user_id' => (int) $adminUser->id,
                ]);
            }
        }

        $salesUsers = [
            [
                'name' => 'Sales User',
                'email' => 'sales@example.com',
                'contact' => '+6400000000000',
                'branch' => 'Yishun',
            ],
            [
                'name' => 'Salbiah',
                'email' => 'salbiah@example.com',
                'contact' => '+6512345678',
                'branch' => 'Golden Landmark',
            ],
        ];

        foreach ($salesUsers as $salesData) {
            $salesUser = User::firstOrCreate(
                ['email' => $salesData['email']],
                [
                    'name' => $salesData['name'],
                    'contact' => $salesData['contact'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $salesUser->assignRole($salesRole);

            $branch = Branch::query()
                ->where('name', $salesData['branch'])
                ->first();

            if ($branch) {
                Sales::firstOrCreate([
                    'user_id' => $salesUser->id,
                    'branch_id' => $branch->id,
                ]);
            }
        }

        $this->command->info('Admin and sales users seeded successfully.');
    }
}
