<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Quotation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::query()->exists()) {
            $this->command->info('Orders already seeded, skipping...');

            return;
        }

        $quotations = Quotation::query()
            ->with('order')
            ->whereIn('status', ['accepted', 'converted'])
            ->orderBy('id')
            ->get();

        if ($quotations->isEmpty()) {
            $this->command->warn('No accepted quotations found for order seeding.');

            return;
        }

        foreach ($quotations as $index => $quotation) {
            if ($quotation->order) {
                continue;
            }

            Order::query()->create([
                'quotation_id' => $quotation->id,
                'payment_plan' => $quotation->payment_plan ?: 'full',
                'handover_date' => Carbon::parse($quotation->quotation_date ?? now())
                    ->addDays(35 + min($index, 10))
                    ->toDateString(),
            ]);

            if ($quotation->status !== 'converted') {
                $quotation->update(['status' => 'converted']);
            }
        }

        $this->command->info('Orders seeded successfully.');
    }
}
