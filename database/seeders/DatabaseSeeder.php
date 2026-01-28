<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Basic Configuration
            AppearanceSettingSeeder::class,
            FinancialYearSeeder::class,
            NumberSequenceSeeder::class,

            // Master Data
            CountrySeeder::class,
            ReligionSeeder::class,
            EducationLevelSeeder::class,
            BranchSeeder::class,
            MasterNotesSeeder::class,
            QuotationItemMasterSeeder::class,

            // Users and Roles
            RolePermissionSeeder::class,
            UserSeeder::class,

            // Business Entities
            SupplierSeeder::class,
            SalesSeeder::class,
            MaidSeeder::class,

            // Transactions
            QuotationSeeder::class,
            OrderSeeder::class,
            InvoiceSeeder::class,
            ReceiptSeeder::class,
            GeneralEnquirySeeder::class,
        ]);
    }
}
