<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $educationLevels = [
            'Junior High School',
            'High School',
        ];

        foreach ($educationLevels as $educationLevel) {
            EducationLevel::firstOrCreate(
                ['name' => $educationLevel]
            );
        }
    }
}
