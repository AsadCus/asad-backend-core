<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['name' => 'Chief Executive Officer', 'code' => 'CEO', 'level' => 'ceo'],
            ['name' => 'Director', 'code' => 'DIRECTOR', 'level' => 'director'],
            ['name' => 'HR Manager', 'code' => 'HR_MANAGER', 'level' => 'manager'],
            ['name' => 'Supervisor', 'code' => 'SUPERVISOR', 'level' => 'supervisor'],
            ['name' => 'HR Officer', 'code' => 'HR_OFFICER', 'level' => 'staff'],
            ['name' => 'Staff', 'code' => 'STAFF', 'level' => 'staff'],
        ];

        foreach ($positions as $position) {
            Position::updateOrCreate(['code' => $position['code']], $position + ['is_active' => true]);
        }
    }
}
