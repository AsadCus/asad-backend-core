<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Country;
use App\Models\GhostUser;
use App\Models\Operation;
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
        $operationsRole = Role::findByName('operations');

        $singaporeCountry = Country::query()->where('name', 'Singapore')->first();
        $malaysiaCountry = Country::query()->where('name', 'Malaysia')->first();

        $singaporeCountryId = $singaporeCountry?->id ? (int) $singaporeCountry->id : null;
        $malaysiaCountryId = $malaysiaCountry?->id ? (int) $malaysiaCountry->id : null;

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
            [
                'name' => 'Kherman',
                'email' => 'kherman@example.com',
                'contact' => '+6400000000000',
            ],
        ];

        $ghostAdminEmails = ['asad@example.com', 'kherman@example.com'];

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

            $selectedCountryIds = [];

            if ($singaporeCountryId !== null) {
                $selectedCountryIds[] = $singaporeCountryId;
            }

            if (
                in_array($adminData['email'], $ghostAdminEmails, true) &&
                $malaysiaCountryId !== null
            ) {
                $selectedCountryIds[] = $malaysiaCountryId;
            }

            $selectedCountryIds = array_values(array_unique($selectedCountryIds));

            Admin::updateOrCreate(
                ['user_id' => (int) $adminUser->id],
                [
                    'branch_id' => null,
                    'country_id' => $selectedCountryIds[0] ?? null,
                    'branch_ids' => [],
                    'country_ids' => $selectedCountryIds,
                ],
            );

            if (in_array($adminData['email'], $ghostAdminEmails, true)) {
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
                $countryIds = $singaporeCountryId !== null
                    ? [$singaporeCountryId]
                    : [];

                Sales::updateOrCreate(
                    ['user_id' => $salesUser->id],
                    [
                        'branch_id' => $branch->id,
                        'country_id' => $singaporeCountryId,
                        'branch_ids' => [$branch->id],
                        'country_ids' => $countryIds,
                    ],
                );
            }
        }

        $operationsUsers = [
            [
                'name' => 'Operations User',
                'email' => 'operations@example.com',
                'contact' => '+6598765432',
            ],
        ];

        foreach ($operationsUsers as $operationsData) {
            $operationsUser = User::firstOrCreate(
                ['email' => $operationsData['email']],
                [
                    'name' => $operationsData['name'],
                    'contact' => $operationsData['contact'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $operationsUser->assignRole($operationsRole);

            $countryIds = $singaporeCountryId !== null
                ? [$singaporeCountryId]
                : [];

            Operation::updateOrCreate(
                ['user_id' => $operationsUser->id],
                [
                    'branch_id' => null,
                    'country_id' => $singaporeCountryId,
                    'branch_ids' => [],
                    'country_ids' => $countryIds,
                ],
            );
        }

        $this->command->info('Admin, sales, and operations users seeded successfully.');
    }
}
