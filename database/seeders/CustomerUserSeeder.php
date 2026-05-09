<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CustomerUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customerRole = Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
        ]);

        $maleNames = [
            'Ahmad Faiz',
            'Muhammad Iqbal',
            'Syafiq Rizal',
            'Farhan Hakim',
            'Hafiz Rahman',
            'Aiman Firdaus',
            'Rizky Pratama',
            'Fikri Hidayat',
            'Dimas Saputra',
            'Bagus Ramadhan',
        ];

        $femaleNames = [
            'Nur Aisyah',
            'Siti Hajar',
            'Ainul Mardhiah',
            'Nabila Syahira',
            'Putri Aulia',
            'Dewi Lestari',
            'Nadya Safitri',
            'Intan Permata',
            'Rina Kartika',
            'Maya Sari',
        ];

        $customerRows = collect($maleNames)
            ->map(fn (string $name): array => [
                'name' => $name,
                'gender' => 'male',
            ])
            ->concat(collect($femaleNames)->map(fn (string $name): array => [
                'name' => $name,
                'gender' => 'female',
            ]))
            ->values();

        $nationalities = ['malaysian', 'singaporean', 'indonesian'];

        foreach ($customerRows as $index => $row) {
            $name = (string) $row['name'];
            $gender = (string) $row['gender'];
            $emailLocal = preg_replace('/[^a-z0-9]+/', '.', strtolower($name));
            $emailLocal = trim((string) $emailLocal, '.');
            $email = $emailLocal.'@example.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'contact' => '+6000000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->assignRole($customerRole);

            Customer::updateOrCreate(
                ['user_id' => (int) $user->id],
                [
                    'nric_number' => 'CUST'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'address' => 'Sample Address '.($index + 1),
                    'nationality' => $nationalities[$index % count($nationalities)],
                    'gender' => $gender,
                    'marital_status' => $index % 2 === 0 ? 'single' : 'married',
                    'date_of_birth' => now()->subYears(22 + $index)->format('Y-m-d'),
                    'place_of_birth' => $index % 2 === 0 ? 'Singapore' : 'Jakarta',
                    'first_time_umrah' => $index % 3 === 0,
                    'has_chronic_disease' => false,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Customer users seeded successfully (20 users, 10 male, 10 female).');
    }
}
