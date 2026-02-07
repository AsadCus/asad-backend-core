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
            ProductionUserSeeder::class,

            GeneralEnquirySeeder::class,
            PrivateEnquirySeeder::class,

            // Travel Management
            PackageSeeder::class,
            ManifestSeeder::class,
        ]);
    }
}
