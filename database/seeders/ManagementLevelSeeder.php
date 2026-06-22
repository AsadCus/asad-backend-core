<?php

namespace Database\Seeders;

use App\Models\ManagementLevel;
use Illuminate\Database\Seeder;

class ManagementLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Top', 'code' => 'TOP', 'color' => '#dc2626', 'sort_order' => 1],
            ['name' => 'Middle', 'code' => 'MID', 'color' => '#d97706', 'sort_order' => 2],
            ['name' => 'Low', 'code' => 'LOW', 'color' => '#2563eb', 'sort_order' => 3],
        ];

        foreach ($levels as $level) {
            ManagementLevel::updateOrCreate(['code' => $level['code']], $level + ['is_active' => true]);
        }
    }
}
