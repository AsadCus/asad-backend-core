<?php

namespace Database\Seeders;

use App\Models\ApprovalMatrix;
use Illuminate\Database\Seeder;

class ApprovalMatrixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * From HRIS_PRESENSI_FLOWCHARTS.md Section 6 (Approval Matrix):
     *   Staff      → Supervisor / Lead → Manager       → HRD
     *   Supervisor → Manager           → —             → HRD
     *   Manager    → Director          → —             → HRD
     *   Director   → CEO               → —             → HRD
     */
    public function run(): void
    {
        $rows = [
            [
                'submitter_level' => 'staff',
                'approver_1_level' => 'supervisor',
                'approver_2_level' => 'manager',
                'final_verifier_role' => 'hr',
            ],
            [
                'submitter_level' => 'supervisor',
                'approver_1_level' => 'manager',
                'approver_2_level' => null,
                'final_verifier_role' => 'hr',
            ],
            [
                'submitter_level' => 'manager',
                'approver_1_level' => 'director',
                'approver_2_level' => null,
                'final_verifier_role' => 'hr',
            ],
            [
                'submitter_level' => 'director',
                'approver_1_level' => 'ceo',
                'approver_2_level' => null,
                'final_verifier_role' => 'hr',
            ],
        ];

        foreach ($rows as $row) {
            ApprovalMatrix::updateOrCreate(
                ['submitter_level' => $row['submitter_level']],
                $row,
            );
        }
    }
}
