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
                'name' => 'Default',
                'prefix' => 'CUST',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'quotation',
                'name' => 'Default',
                'prefix' => 'QTN',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'order',
                'name' => 'Default',
                'prefix' => 'OR',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'invoice',
                'name' => 'Default',
                'prefix' => 'INV',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'receipt',
                'name' => 'Default',
                'prefix' => 'R',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'package',
                'name' => 'Default',
                'prefix' => 'KTG',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'manifest',
                'name' => 'Default',
                'prefix' => 'KTG-UMR',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 3,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'customer_confirmation',
                'name' => 'Default',
                'prefix' => 'CC',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
                'increment_padding' => 4,
                'increment_start' => 1,
                'increment_scope' => 'format',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'model_key' => 'maid',
                'name' => 'Default',
                'prefix' => 'MD',
                'separator' => '-',
                'include_year' => true,
                'year_format' => 'Y',
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
