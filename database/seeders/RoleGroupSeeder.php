<?php

namespace Database\Seeders;

use App\Models\RoleGroup;
use Illuminate\Database\Seeder;

class RoleGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'Leadership', 'code' => 'LEAD', 'description' => 'Executives and managers', 'color' => '#7c3aed', 'sort_order' => 1],
            ['name' => 'Human Resources', 'code' => 'HRADM', 'description' => 'HR and administration', 'color' => '#0891b2', 'sort_order' => 2],
            ['name' => 'General', 'code' => 'GEN', 'description' => 'General staff', 'color' => '#64748b', 'sort_order' => 3],
        ];

        foreach ($groups as $group) {
            RoleGroup::updateOrCreate(['code' => $group['code']], $group + ['is_active' => true]);
        }
    }
}
