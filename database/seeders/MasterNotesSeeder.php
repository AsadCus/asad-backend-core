<?php

namespace Database\Seeders;

use App\Models\MasterNotes;
use Illuminate\Database\Seeder;

class MasterNotesSeeder extends Seeder
{
    public function run(): void
    {
        $notes = [
            // Quotation Notes
            [
                'model' => 'quotation',
                'description' => '50% refund of Service Fee within 6 months if employer decided to terminate the contract & MDW must return to agency for Transfer (Employer to sign/authorise the consent of transfer online)',
                'sort_order' => 1,
            ],
            [
                'model' => 'quotation',
                'description' => '2 Free Replacements within 6 months',
                'sort_order' => 2,
            ],
            [
                'model' => 'quotation',
                'description' => 'For every replacement, the employer will need to pay: Top Up difference in Agency Fee + Processing Fee + Documentation Fee + WPOL Filing Fee + SIP (if needed) + Transport & Facilitation Fee + Insurance Fee + Any Placement Fee Top Up + Embassy Contract Fee (if needed) * Loan/Placement Fee (Upfront Payment)',
                'sort_order' => 3,
            ],

            // Invoice Notes
            [
                'model' => 'invoice',
                'description' => 'Payment due within 14 days',
                'sort_order' => 1,
            ],
            [
                'model' => 'invoice',
                'description' => 'Late payment may incur additional charges',
                'sort_order' => 2,
            ],
            [
                'model' => 'invoice',
                'description' => 'Please include invoice number in payment reference',
                'sort_order' => 3,
            ],

            // Receipt Notes
            [
                'model' => 'receipt',
                'description' => 'Thank you for your payment',
                'sort_order' => 1,
            ],
            [
                'model' => 'receipt',
                'description' => 'Please keep this receipt for your records',
                'sort_order' => 2,
            ],
            [
                'model' => 'receipt',
                'description' => 'For any queries, please contact our office',
                'sort_order' => 3,
            ],
        ];

        foreach ($notes as $note) {
            MasterNotes::create($note);
        }

        $this->command->info('Master note created successfully!');
    }
}
