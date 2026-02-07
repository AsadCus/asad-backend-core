<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Sales;
use App\Models\Customer;
use App\Models\Supplier;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
                'name' => 'Shalenny',
                'email' => 'lennyradzali@urbancare-agency.com',
                'contact' => '81131302',
                'password' => Hash::make('password'),
                'role' => 'sales',
                'branch_id' => 1,
            ],
            [
                'name' => 'Jun',
                'email' => 'junaidah@urbancare-agency.com',
                'contact' => '84843322',
                'password' => Hash::make('password'),
                'role' => 'sales',
                'branch_id' => 1,
            ],
            [
                'name' => 'Batrisha',
                'email' => 'batrisha@urbancare-agency.com',
                'contact' => '94898321',
                'password' => Hash::make('password'),
                'role' => 'sales',
                'branch_id' => 1,
            ],
        ];

        // Customer Users
        $customerUsers = [
            [
                'name' => 'Isabelle',
                'email' => 'isabelleteo90@gmail.com',
                'contact' => '96523021',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'customer_number' => 'UC-2026-0017',
                'nric_number' => 'S9020807A',
                'branch_id' => 1,
            ],
            [
                'name' => 'Muhammad Fadzly Bin Md Yusof',
                'email' => 'fadzly_gg@yahoo.com.sg',
                'contact' => '94350185',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'customer_number' => 'UC-2026-0019',
                'nric_number' => 'S8331304H',
                'address' => '512B Yishun St 51<br>#03-485<br>Singapore 762512',
                'branch_id' => 1,
            ],
            [
                'name' => 'Farida Binte Mohd Ali Akbar',
                'email' => 'adikfreda@hotmail.com',
                'contact' => '+65 8693 1160',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'customer_number' => 'UC-2026-0021',
                'nric_number' => 'S7802749E',
                'address' => 'Blk 856 <br>Jurong West St 81 #06-544 <br>Singapore 640856',
                'branch_id' => 1,
            ],
            [
                'name' => 'Hesley Bte Ismail',
                'email' => 'skizocase@gmail.com',
                'contact' => '+6597641644',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'customer_number' => 'UC-2026-0022',
                'nric_number' => null,
                'branch_id' => 1,
            ],
        ];

        // Supplier Users
        $supplierUsers = [
            [
                'name' => 'Riswan',
                'email' => 'riswanalzubara74@gmail.com',
                'contact' => '+6281211653044',
                'password' => Hash::make('password'),
                'role' => 'supplier',
                'supplier_name' => 'PT Al Zubara Manpower Indonesia',
                'address' => 'Jalan Masjid Al Ikhsan no.56<br>RT 02/03 - Kel/ Jati Bening<br>Jawa Barat 17412, Indonesia',
            ],
            [
                'name' => 'Bertha',
                'email' => 'lintasdian@gmail.com',
                'contact' => '+6282297212191',
                'password' => Hash::make('password'),
                'role' => 'supplier',
                'supplier_name' => 'PT Lintas Cakrawala Buana',
                'address' => 'JL. DAAN MOGOT RAYA KM 12 BLOK 12 CENGKARENG TIMUR JAKARTA BARAT',
            ],
            [
                'name' => 'Dian Mayasari Rosdan',
                'email' => 'dianmayasari@gmail.com',
                'contact' => '+6287884811707',
                'password' => Hash::make('password'),
                'role' => 'supplier',
                'supplier_name' => 'Dian Manpower Supply',
                'address' => 'Jalan Bandung 2, Desa/Kelurahan Jatisampura, Kec. Jatisampurna Kota Bekasi Provinsi Jawa Barat',
            ],
            [
                'name' => 'Pak Haji Abdul',
                'email' => 'hajiabdul@gmail.com',
                'contact' => '+6285338411664',
                'password' => Hash::make('password'),
                'role' => 'supplier',
                'supplier_name' => 'Haji Abdul Supplier',
                'address' => 'Indonesia',
            ],
            [
                'name' => 'ALL LINK',
                'email' => 'ivan.allink@gmail.com',
                'contact' => '96180056',
                'password' => Hash::make('password'),
                'role' => 'supplier',
                'supplier_name' => 'ALL LINK Supplier',
                'address' => 'Singapore',
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
