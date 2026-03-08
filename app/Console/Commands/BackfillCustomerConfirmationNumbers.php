<?php

namespace App\Console\Commands;

use App\Helpers\NumberGenerator;
use App\Models\CustomerConfirmation;
use Illuminate\Console\Command;

class BackfillCustomerConfirmationNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer-confirmations:backfill-numbers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill number field for customer confirmations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $confirmations = CustomerConfirmation::whereNull('number')->get();

        if ($confirmations->isEmpty()) {
            $this->info('No customer confirmations found without numbers.');

            return Command::SUCCESS;
        }

        $this->info("Found {$confirmations->count()} customer confirmation(s) without numbers.");

        $bar = $this->output->createProgressBar($confirmations->count());
        $bar->start();

        foreach ($confirmations as $confirmation) {
            $confirmation->update([
                'number' => NumberGenerator::generate('customer_confirmation'),
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Customer confirmation numbers backfilled successfully!');

        return Command::SUCCESS;
    }
}
