<?php

namespace Database\Seeders;

use App\Models\PaymentMethodMaster;
use Illuminate\Database\Seeder;

class PaymentMethodMasterSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'Cash', 'value' => 'cash', 'sort_order' => 1, 'is_default' => true],
            ['name' => 'Nets', 'value' => 'nets', 'sort_order' => 2, 'is_default' => false],
            ['name' => 'Visa', 'value' => 'visa', 'sort_order' => 3, 'is_default' => false],
            ['name' => 'Master', 'value' => 'master', 'sort_order' => 4, 'is_default' => false],
            ['name' => 'Paynow', 'value' => 'paynow', 'sort_order' => 5, 'is_default' => true],
        ];

        foreach ($methods as $method) {
            PaymentMethodMaster::query()->updateOrCreate(
                ['value' => $method['value']],
                [
                    'name' => $method['name'],
                    'is_active' => true,
                    'is_default' => (bool) $method['is_default'],
                    'sort_order' => (int) $method['sort_order'],
                ],
            );
        }

        PaymentMethodMaster::query()
            ->whereNotIn('value', array_column($methods, 'value'))
            ->update(['is_default' => false]);
    }
}
