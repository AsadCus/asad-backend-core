<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database (ERP-HRIS).
     */
    public function run(): void
    {
        $this->call([
            // App configuration
            AppearanceSettingSeeder::class,
            FinancialYearSeeder::class,
            NumberingFormatSeeder::class,
            ReportSettingSeeder::class,

            // General master data
            CountrySeeder::class,
            ReligionSeeder::class,
            EducationLevelSeeder::class,
            BranchSeeder::class,
            PaymentMethodMasterSeeder::class,

            // Recursive org tree (holding → BU → branch → department → division)
            OrgUnitSeeder::class,
            OrgInfoSeeder::class,

            // Role classification masters (must exist before roles get their metadata)
            ManagementLevelSeeder::class,
            RoleGroupSeeder::class,

            // Roles + users (roles + org must exist first)
            RolePermissionSeeder::class,
            HrisRoleSeeder::class,
            UserSeeder::class,

            // HRIS reference data
            ApprovalMatrixSeeder::class,
            LeaveTypeSeeder::class,
            ShiftSeeder::class,
            WorkScheduleSeeder::class,
            HolidaySeeder::class,

            // Menu defaults
            MenuOverrideSeeder::class,

            // Multi-BU demo accounts + WFH/Visit permissions (run after roles + work schedules)
            DemoSeeder::class,
        ]);
    }
}
