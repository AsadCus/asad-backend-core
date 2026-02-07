<?php

namespace App\Console\Commands;

use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestFinancialYearRollover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financial:test-rollover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the financial year rollover functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Financial Year Rollover Test ===');
        $this->newLine();

        // Show current state
        $years = FinancialYear::orderBy('year')->get();

        if ($years->isEmpty()) {
            $this->warn('No financial years found in database.');
            $this->info('Creating initial financial year...');

            $year = FinancialYear::create([
                'year' => (string) date('Y'),
                'start_date' => Carbon::now()->startOfYear()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfYear()->format('Y-m-d'),
                'default' => true,
                'is_active' => true,
            ]);

            $this->info("Created FY {$year->year}: {$year->start_date} to {$year->end_date}");
            $years = FinancialYear::orderBy('year')->get();
        }

        $this->info('Current Financial Years:');
        $headers = ['ID', 'Year', 'Start Date', 'End Date', 'Default', 'Active'];
        $rows = $years->map(function ($year) {
            return [
                $year->id,
                $year->year,
                $year->start_date,
                $year->end_date,
                $year->default ? '✓' : '✗',
                $year->is_active ? '✓' : '✗',
            ];
        })->toArray();

        $this->table($headers, $rows);

        // Test progression
        $currentYear = FinancialYear::getCurrentYear();
        if ($currentYear) {
            $this->info("Current active year: FY {$currentYear->year}");
            $this->newLine();

            if ($this->confirm('Do you want to test the rollover to the next financial year?', false)) {
                $this->info('Starting rollover test...');

                try {
                    DB::beginTransaction();

                    $nextYear = FinancialYear::progressFinancialYear();

                    $this->info("New financial year created: FY {$nextYear->year}");
                    $this->info("Period: {$nextYear->start_date} to {$nextYear->end_date}");

                    // Show updated state
                    $years = FinancialYear::orderBy('year')->get();
                    $rows = $years->map(function ($year) {
                        return [
                            $year->id,
                            $year->year,
                            $year->start_date,
                            $year->end_date,
                            $year->default ? '✓' : '✗',
                            $year->is_active ? '✓' : '✗',
                        ];
                    })->toArray();

                    $this->newLine();
                    $this->info('Updated Financial Years:');
                    $this->table($headers, $rows);

                    if ($this->confirm('Do you want to KEEP these changes?', false)) {
                        DB::commit();
                        $this->info('✓ Changes committed successfully');
                    } else {
                        DB::rollBack();
                        $this->info('✓ Changes rolled back');
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Error during rollover: {$e->getMessage()}");
                    $this->error($e->getTraceAsString());
                }
            }
        } else {
            $this->warn('No active financial year found. Please create one first.');
        }

        return 0;
    }
}
