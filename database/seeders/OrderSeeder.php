<?php

namespace Database\Seeders;

use App\Models\CustomerConfirmationMember;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Seeder;

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
            ]);

            if ($quotation->status !== 'converted') {
                $quotation->update(['status' => 'converted']);
            }
        }

        $confirmedMemberIds = CustomerConfirmationMember::query()
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->pluck('id')
            ->all();

        if (! empty($confirmedMemberIds)) {
            $memberQuotationIds = QuotationItem::query()
                ->whereIn('customer_confirmation_member_id', $confirmedMemberIds)
                ->pluck('quotation_id')
                ->unique()
                ->values();

            $memberQuotations = Quotation::query()
                ->with('order')
                ->whereIn('id', $memberQuotationIds)
                ->get();

            foreach ($memberQuotations as $quotation) {
                if ($quotation->order) {
                    continue;
                }

                Order::query()->create([
                    'quotation_id' => $quotation->id,
                    'payment_plan' => $quotation->payment_plan ?: 'full',
                ]);

                if ($quotation->status !== 'converted') {
                    $quotation->update(['status' => 'converted']);
                }
            }
        }

        $this->command->info('Orders seeded successfully.');
    }
}
