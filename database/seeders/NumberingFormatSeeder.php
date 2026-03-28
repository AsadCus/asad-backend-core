<?php

namespace Database\Seeders;

use App\Models\NumberingFormat;
use Illuminate\Database\Seeder;

class NumberingFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            [
                'model_key' => 'customer',
                'name' => 'CUST-%YYYY%-%I%',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'quotation',
                'name' => 'QTN-%YYYY%-%I%',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'order',
                'name' => 'OR-%YYYY%-%I%',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'invoice',
                'name' => 'INV-%YYYY%-%I%',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'receipt',
                'name' => 'R-%YYYY%-%I%',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'package',
                'name' => 'KTG-%YYYY%-%I%',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'manifest',
                'name' => 'KTG-UMR-%YYYY%-%I%',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'customer_confirmation',
                'name' => 'CC-%YYYY%-%I%',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'maid',
                'name' => 'MD-%YYYY%-%I%',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($defaults as $row) {
            NumberingFormat::query()->updateOrCreate(
                [
                    'model_key' => $row['model_key'],
                    'name' => $row['name'],
                ],
                $row,
            );
        }
    }
}
