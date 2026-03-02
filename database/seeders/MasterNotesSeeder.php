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
                'description' => 'Any further changes to the quotation may affect the final price',
                'sort_order' => 1,
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
