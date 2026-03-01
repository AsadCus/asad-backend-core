<?php

namespace Database\Seeders;

use App\Models\QuotationItemMaster;
use Illuminate\Database\Seeder;

class QuotationItemMasterSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'description' => 'Travel Package Fee',
                'is_header' => true,
                'is_optional' => true,
            ],
            [
                'parent_id' => 1,
                'description' => 'Package Cost (per sharing plan)',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 0,
            ],
            [
                'description' => 'Additional Services',
                'is_header' => true,
                'is_optional' => true,
            ],
            [
                'parent_id' => 3,
                'description' => 'Travel Insurance',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 0,
            ],
            [
                'parent_id' => 3,
                'description' => 'Visa Processing Fee',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 0,
            ],
            [
                'parent_id' => 3,
                'description' => 'Airport Transfer',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 0,
            ],
            [
                'description' => 'Miscellaneous',
                'is_header' => true,
                'is_optional' => true,
            ],
            [
                'parent_id' => 7,
                'description' => 'Additional Accommodation',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 0,
            ],
            [
                'parent_id' => 7,
                'description' => 'Special Meal Request',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 0,
            ],
        ];

        $sortOrderItem = 1;

        foreach ($items as $item) {
            QuotationItemMaster::create([
                ...$item,
                'sort_order' => $sortOrderItem++,
            ]);
        }

        $this->command->info('Quotation master item created successfully!');
    }
}
