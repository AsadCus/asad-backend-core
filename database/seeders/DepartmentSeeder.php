<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $businessUnit = BusinessUnit::query()->where('code', 'BU-TECH')->first();

        if (! $businessUnit) {
            return;
        }

        $departments = [
            ['name' => 'Human Resources', 'code' => 'DEPT-HR'],
            ['name' => 'Engineering', 'code' => 'DEPT-ENG'],
            ['name' => 'Operations', 'code' => 'DEPT-OPS'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                $department + ['business_unit_id' => $businessUnit->id, 'is_active' => true],
            );
        }
    }
}
