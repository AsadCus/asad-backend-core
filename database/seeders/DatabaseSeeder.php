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
            NumberingFormatSeeder::class,
            ReportSettingSeeder::class,

            // Master Data
            CountrySeeder::class,
            ReligionSeeder::class,
            EducationLevelSeeder::class,
            BranchSeeder::class,
            MasterNotesSeeder::class,
            QuotationItemMasterSeeder::class,
            PaymentMethodMasterSeeder::class,

            // Users and Roles
            RolePermissionSeeder::class,
            AdminSalesUserSeeder::class,
            // UserSeeder::class,

            // // Travel Management
            // PackageSeeder::class,
            // EnquirySeeder::class,
            // CustomerConfirmationSeeder::class,

            // // Transactions
            // QuotationSeeder::class,
            // OrderSeeder::class,
            // InvoiceSeeder::class,
            // ReceiptSeeder::class,
            // ManifestSeeder::class,
        ]);
    }
}
