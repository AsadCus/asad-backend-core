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
                'description' => 'Service Fee',
                'is_header' => true
            ],
            [
                'parent_id' => 1,
                'description' => 'Sourcing & Recruitment Fee',
                'quantity' => 1,
                'rate' => 699,
            ],
            [
                'parent_id' => 1,
                'description' => 'Discount',
                'quantity' => 1,
                'rate' => -400,
            ],
            [
                'description' => 'Third Party Fee',
                'is_header' => true,
            ],
            [
                'parent_id' => 4,
                'description' => 'Work Pass (WPOL)',
                'quantity' => 1,
                'rate' => 35,
            ],
            [
                'parent_id' => 4,
                'description' => 'Work Pass (E-Issuance)',
                'quantity' => 1,
                'rate' => 35,
            ],
            [
                'parent_id' => 4,
                'description' => 'Settling In Programme',
                'quantity' => 1,
                'rate' => 76.40,
            ],
            [
                'description' => 'Medical + X-ray',
                'quantity' => 1,
                'rate' => 84,
            ],
            [
                'description' => 'Transport in Singapore',
                'is_header' => true,
            ],
            [
                'parent_id' => 9,
                'description' => 'Airport to Agency',
                'quantity' => 1,
                'rate' => 50,
            ],
            [
                'parent_id' => 9,
                'description' => 'Agency to MOM (Thumbprint)',
                'quantity' => 1,
                'rate' => 50,
            ],
            [
                'parent_id' => 9,
                'description' => 'Agency to SIP',
                'quantity' => 1,
                'rate' => 50,
            ],
            [
                'parent_id' => 9,
                'description' => 'Agency to Medical',
                'quantity' => 1,
                'rate' => 50,
            ],
            [
                'description' => 'Local Documentation in Home Country + Overseas Clearance Fee',
                'quantity' => 1,
                'rate' => 464,
            ],
            [
                'description' => 'Pre Deployment Lodging Fees',
                'quantity' => 1,
                'rate' => 95,
            ],
            [
                'description' => 'In-House Training',
                'quantity' => 1,
                'rate' => 0,
            ],
            [
                'description' => 'MDW Insurance - Silver Plan by Great Eastern (Non-Transferable)',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 521.02,
            ],
            [
                'description' => 'Indemnity Insurance (Non-Transferable)',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 54.5,
            ],
            [
                'description' => '1 day Basic Caregiver Training - learn how to assist on daily activities and medical needs (non-subsidised)',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 150,
            ],
            [
                'description' => '1 day Infant Care course (non-subsidised)',
                'is_optional' => true,
                'quantity' => 1,
                'rate' => 150,
            ],
            [
                'description' => 'Employer shall be liable to deploy the MDW on the 4th day from the arrival date $25.00 per day for extension of Food & Accomodation',
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
