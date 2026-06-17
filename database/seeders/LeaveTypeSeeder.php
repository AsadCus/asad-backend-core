<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Annual Leave',
                'code' => 'ANNUAL',
                'max_days_per_year' => 12,
                'requires_balance' => true,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Standard yearly paid leave entitlement.',
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'SICK',
                'max_days_per_year' => 12,
                'requires_balance' => true,
                'requires_attachment' => true,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Medical leave; attach doctor letter if requested.',
            ],
            [
                'name' => 'Maternity Leave',
                'code' => 'MATERNITY',
                'max_days_per_year' => 90,
                'requires_balance' => false,
                'requires_attachment' => true,
                'is_paid' => true,
                'gender_restriction' => 'female',
                'description' => 'Up to 90 paid days for childbirth and recovery.',
            ],
            [
                'name' => 'Paternity Leave',
                'code' => 'PATERNITY',
                'max_days_per_year' => 3,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => 'male',
                'description' => 'Paid leave for new fathers.',
            ],
            [
                'name' => 'Bereavement Leave',
                'code' => 'BEREAVEMENT',
                'max_days_per_year' => 3,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Paid leave for the death of an immediate family member.',
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'UNPAID',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => false,
                'gender_restriction' => null,
                'description' => 'Approved leave without pay (no cap).',
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type + ['is_active' => true]);
        }
    }
}
