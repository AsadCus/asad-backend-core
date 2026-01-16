<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanMaidSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Backup Existing maid data
        $existingData = DB::table('maids')->get();

        // Clean and update each maid record
        foreach ($existingData as $maid) {
            $cleanData = [
                'employment_history' => $this->cleanJson($maid->employment_history),
                'skills_assessment' => $this->cleanJson($maid->skills_assessment),
                'skills_assessment_numeric' => $this->cleanJson($maid->skills_assessment_numeric),
                'skills_assessment_qualitative' => $this->cleanJson($maid->skills_assessment_qualitative),
            ];

            // Update the maid record with cleaned data
            DB::table('maids')->where('id', $maid->id)->update($cleanData);
        }
    }

    private function cleanJson($jsonString)
    {
        if (empty($jsonString) || $jsonString === 'null') {
            return null;
        }

        // If already an array, encode it properly
        if (is_array($jsonString)) {
            return json_encode($jsonString);
        }

        // Remove double escaping: "" -> "
        $cleaned = str_replace('""', '"', $jsonString);
        
        // Try to decode
        $decoded = json_decode($cleaned, true);
        
        // If successful, re-encode cleanly
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return json_encode($decoded);
        }
        
        // If still fails, try one more decode (double-encoded case)
        if (is_string($decoded)) {
            $doubleDecoded = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE && $doubleDecoded !== null) {
                return json_encode($doubleDecoded);
            }
        }
        
        // Return null if all attempts fail
        return null;
    }
}
