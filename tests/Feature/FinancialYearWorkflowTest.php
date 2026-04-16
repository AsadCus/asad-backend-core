<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Services\FinancialTransactionService;
use App\Services\FinancialYearService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialYearWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_initial_fiscal_year_from_day_month_and_sets_default(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        try {
            $financialYear = app(FinancialYearService::class)->store([
                'start_day' => 1,
                'start_month' => 1,
                'end_day' => 31,
                'end_month' => 12,
            ]);

            $this->assertSame('2026', (string) $financialYear->year);
            $this->assertSame('2026-01-01', (string) $financialYear->start_date?->toDateString());
            $this->assertSame('2026-12-31', (string) $financialYear->end_date?->toDateString());
            $this->assertTrue((bool) $financialYear->default);
            $this->assertTrue((bool) $financialYear->is_active);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_store_throws_validation_error_when_active_fiscal_year_exists(): void
    {
        FinancialYear::create([
            'year' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'default' => true,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(FinancialYearService::class)->store([
            'start_day' => 1,
            'start_month' => 1,
            'end_day' => 31,
            'end_month' => 12,
        ]);
    }

    public function test_update_does_not_reassign_existing_financial_transactions(): void
    {
        $financialYear = FinancialYear::create([
            'year' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'default' => true,
            'is_active' => true,
        ]);

        $transaction = FinancialTransaction::create([
            'financial_year_id' => $financialYear->id,
            'type' => 'revenue',
            'amount' => 150.0,
            'description' => 'Before fiscal edit',
            'transaction_date' => '2027-01-15',
        ]);

        app(FinancialYearService::class)->update([
            'start_day' => 31,
            'start_month' => 1,
            'end_day' => 30,
            'end_month' => 1,
        ], $financialYear->id);

        $transaction->refresh();
        $financialYear->refresh();

        $this->assertSame((int) $financialYear->id, (int) $transaction->financial_year_id);
        $this->assertSame('2027-01-31', (string) $financialYear->start_date?->toDateString());
        $this->assertSame('2028-01-30', (string) $financialYear->end_date?->toDateString());
    }

    public function test_progress_financial_year_creates_next_period_based_on_default_pattern(): void
    {
        FinancialYear::create([
            'year' => '2027',
            'start_date' => '2027-02-20',
            'end_date' => '2028-02-19',
            'default' => true,
            'is_active' => true,
        ]);

        $resolvedYear = FinancialYear::progressFinancialYear(Carbon::parse('2028-02-20'));

        $this->assertNotNull($resolvedYear);
        $this->assertSame('2028', (string) $resolvedYear?->year);
        $this->assertSame('2028-02-20', (string) $resolvedYear?->start_date?->toDateString());
        $this->assertSame('2029-02-19', (string) $resolvedYear?->end_date?->toDateString());
        $this->assertTrue((bool) $resolvedYear?->default);

        $this->assertDatabaseHas('financial_years', [
            'year' => '2027',
            'default' => false,
        ]);
    }

    public function test_overlap_transactions_attach_to_current_default_year_at_creation_time(): void
    {
        Carbon::setTestNow('2029-01-15 10:00:00');

        try {
            $fy2028 = FinancialYear::create([
                'year' => '2028',
                'start_date' => '2028-01-31',
                'end_date' => '2029-01-30',
                'default' => true,
                'is_active' => true,
            ]);

            $fy2029 = FinancialYear::create([
                'year' => '2029',
                'start_date' => '2029-01-01',
                'end_date' => '2029-12-31',
                'default' => false,
                'is_active' => true,
            ]);

            $service = app(FinancialTransactionService::class);

            $firstTransaction = $service->recordRevenue(
                200.0,
                'Overlap transaction when 2028 is default',
                Carbon::parse('2029-01-15')
            );

            $this->assertSame((int) $fy2028->id, (int) $firstTransaction->financial_year_id);

            $fy2028->update(['default' => false]);
            $fy2029->update(['default' => true]);

            $secondTransaction = $service->recordRevenue(
                300.0,
                'Overlap transaction when 2029 is default',
                Carbon::parse('2029-01-15')
            );

            $this->assertSame((int) $fy2029->id, (int) $secondTransaction->financial_year_id);
        } finally {
            Carbon::setTestNow();
        }
    }
}
