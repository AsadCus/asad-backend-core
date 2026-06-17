<?php

namespace Database\Seeders\Tms;

use App\Models\QuotationItemMaster;
use Illuminate\Database\Seeder;

class QuotationItemMasterSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'Umrah Packages',
            'Leisure Packages',
            'Aqiqah',
            'Badal Umrah',
            'Firday Blessings / Badal',
            'Sabeel',
            'Others',
        ];

        $sortOrderItem = 1;

        foreach ($items as $item) {
            QuotationItemMaster::create([
                'parent_id' => null,
                'description' => $item,
                'is_header' => true,
                'is_optional' => true,
                'quantity' => null,
                'rate' => null,
                'sort_order' => $sortOrderItem++,
            ]);
        }

        $this->command->info('Quotation master item created successfully!');
    }
}
