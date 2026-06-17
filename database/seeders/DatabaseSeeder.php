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

            // HRIS organisation masters (order matters: holding → BU → department)
            HoldingSeeder::class,
            BusinessUnitSeeder::class,
            DepartmentSeeder::class,
            PositionSeeder::class,

            // Roles + users (roles + org/positions must exist first)
            RolePermissionSeeder::class,
            HrisRoleSeeder::class,
            UserSeeder::class,

            // HRIS reference data
            ApprovalMatrixSeeder::class,
            LeaveTypeSeeder::class,
            ShiftSeeder::class,
            WorkScheduleSeeder::class,
            HolidaySeeder::class,
        ]);
    }
}
