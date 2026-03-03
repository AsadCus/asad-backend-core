<?php

namespace Database\Seeders;

use App\Models\NumberSequence;
use Illuminate\Database\Seeder;

class NumberSequenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = date('Y');

        $sequences = [
            ['type' => 'customer', 'year' => $currentYear, 'current_number' => 0],
            ['type' => 'maid', 'year' => $currentYear, 'current_number' => 0],
            ['type' => 'quotation', 'year' => $currentYear, 'current_number' => 0],
            ['type' => 'invoice', 'year' => $currentYear, 'current_number' => 0],
            ['type' => 'receipt', 'year' => $currentYear, 'current_number' => 0],
            ['type' => 'order', 'year' => $currentYear, 'current_number' => 0],
        ];

        foreach ($sequences as $sequence) {
            NumberSequence::create($sequence);
        }
    }
}
