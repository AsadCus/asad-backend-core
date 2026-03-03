<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Invoice;
use Carbon\Carbon;

class FinancialTransactionService
{
    /**
     * Record an expense transaction
     */
    public function recordExpense(
        float $amount,
        string $description,
        Carbon $date,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): FinancialTransaction {
        $financialYear = $this->getFinancialYearForDate($date);

        if (! $financialYear) {
            throw new \Exception('No financial year found for the given date');
        }

        return FinancialTransaction::create([
            'financial_year_id' => $financialYear->id,
            'type' => 'expense',
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_date' => $date,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a revenue transaction
     */
    public function recordRevenue(
        float $amount,
        string $description,
        Carbon $date,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): FinancialTransaction {
        $financialYear = $this->getFinancialYearForDate($date);

        if (! $financialYear) {
            throw new \Exception('No financial year found for the given date');
        }

        return FinancialTransaction::create([
            'financial_year_id' => $financialYear->id,
            'type' => 'revenue',
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_date' => $date,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get financial year for a specific date
     * Auto-creates financial year if it doesn't exist
     */
    private function getFinancialYearForDate(Carbon $date): ?FinancialYear
    {
        return FinancialYear::getOrCreateForDate($date);
    }

    /**
     * Get total expenses for a financial year
     */
    public function getTotalExpenses(int $financialYearId): float
    {
        return FinancialTransaction::forYear($financialYearId)->expenses()->sum('amount');
    }

    /**
     * Get total revenue for a financial year
     */
    public function getTotalRevenue(int $financialYearId): float
    {
        return FinancialTransaction::forYear($financialYearId)->revenue()->sum('amount');
    }

    /**
     * Get monthly breakdown of expenses and revenue
     */
    public function getMonthlyBreakdown(int $financialYearId): array
    {
        $financialYear = FinancialYear::findOrFail($financialYearId);
        $startDate = Carbon::parse($financialYear->start_date);
        $endDate = Carbon::parse($financialYear->end_date);

        $months = [];
        $currentMonth = $startDate->copy();

        while ($currentMonth->lte($endDate)) {
            $monthStart = $currentMonth->copy();
            $monthEnd = $currentMonth->copy()->addMonth()->subDay();

            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate->copy();
            }

            $expenses = FinancialTransaction::expenses()->dateRange($monthStart, $monthEnd)->sum('amount');
            $revenue = FinancialTransaction::revenue()->dateRange($monthStart, $monthEnd)->sum('amount');

            $months[] = [
                'month' => $monthStart->format('M Y'),
                'date' => $monthStart->format('Y-m-d'),
                'expenses' => (float) $expenses,
                'revenue' => (float) $revenue,
                'profit' => (float) ($revenue - $expenses),
            ];

            $currentMonth->addMonth();
        }

        return $months;
    }

    /**
     * Get last N months of financial data from current date
     * Uses fiscal month boundaries based on fiscal year start date
     */
    public function getLastMonthsBreakdown(int $months): array
    {
        $result = [];
        $now = Carbon::now();

        $currentFiscalYear = FinancialYear::getCurrentYear();

        if (! $currentFiscalYear) {
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = $now->copy()->subMonths($i);
                $monthStart = $date->copy()->startOfMonth();
                $monthEnd = $date->copy()->endOfMonth();

                $expenses = FinancialTransaction::whereBetween('transaction_date', [$monthStart, $monthEnd])
                    ->where('type', 'expense')
                    ->sum('amount');

                $revenue = FinancialTransaction::whereBetween('transaction_date', [$monthStart, $monthEnd])
                    ->where('type', 'revenue')
                    ->sum('amount');

                $result[] = [
                    'month' => $monthStart->format('M Y'),
                    'date' => $monthStart->format('Y-m-d'),
                    'expenses' => (float) $expenses,
                    'revenue' => (float) $revenue,
                    'profit' => (float) ($revenue - $expenses),
                ];
            }

            return $result;
        }

        $fiscalDayOfMonth = $currentFiscalYear->start_date->day;

        if ($now->day >= $fiscalDayOfMonth) {
            $currentFiscalMonthStart = Carbon::create($now->year, $now->month, $fiscalDayOfMonth);
        } else {
            $currentFiscalMonthStart = Carbon::create($now->year, $now->month, $fiscalDayOfMonth)->subMonth();
        }

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $currentFiscalMonthStart->copy()->subMonths($i);
            $monthEnd = $monthStart->copy()->addMonth()->subDay();

            $expenses = FinancialTransaction::whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->where('type', 'expense')
                ->sum('amount');

            $revenue = FinancialTransaction::whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->where('type', 'revenue')
                ->sum('amount');

            $result[] = [
                'month' => $monthStart->format('M Y'),
                'date' => $monthStart->format('Y-m-d'),
                'expenses' => (float) $expenses,
                'revenue' => (float) $revenue,
                'profit' => (float) ($revenue - $expenses),
            ];
        }

        return $result;
    }

    /**
     * Get quarterly breakdown
     */
    public function getQuarterlyBreakdown(int $financialYearId): array
    {
        $financialYear = FinancialYear::findOrFail($financialYearId);
        $startDate = Carbon::parse($financialYear->start_date);
        $endDate = Carbon::parse($financialYear->end_date);

        $quarters = [];
        $quarterStart = $startDate->copy();

        for ($i = 1; $i <= 4; $i++) {
            $quarterEnd = $quarterStart->copy()->addMonths(3)->subDay();

            if ($quarterEnd->gt($endDate)) {
                $quarterEnd = $endDate->copy();
            }

            $expenses = FinancialTransaction::forYear($financialYearId)
                ->expenses()
                ->dateRange($quarterStart, $quarterEnd)
                ->sum('amount');

            $revenue = FinancialTransaction::forYear($financialYearId)
                ->revenue()
                ->dateRange($quarterStart, $quarterEnd)
                ->sum('amount');

            $quarters[] = [
                'quarter' => "Q{$i}",
                'period' => $quarterStart->format('d M Y').' - '.$quarterEnd->format('d M Y'),
                'expenses' => (float) $expenses,
                'revenue' => (float) $revenue,
                'profit' => (float) ($revenue - $expenses),
            ];

            $quarterStart = $quarterEnd->copy()->addDay();

            if ($quarterStart->gt($endDate)) {
                break;
            }
        }

        return $quarters;
    }

    /**
     * Get financial summary for current year
     */
    public function getCurrentYearSummary(): array
    {
        $currentYear = FinancialYear::getCurrentYear();

        if (! $currentYear) {
            return [
                'total_expenses' => 0,
                'total_revenue' => 0,
                'profit' => 0,
                'fiscal_year' => null,
            ];
        }

        $expenses = $this->getTotalExpenses($currentYear->id);
        $revenue = $this->getTotalRevenue($currentYear->id);

        return [
            'total_expenses' => $expenses,
            'total_revenue' => $revenue,
            'profit' => $revenue - $expenses,
            'fiscal_year' => $currentYear->year,
        ];
    }

    /**
     * Update maid expense transaction
     */
    public function updateMaidExpense($maid): void
    {
        $transaction = FinancialTransaction::where('reference_type', 'App\Models\Maid')->where('reference_id', $maid->id)->first();

        if ($transaction) {
            $newCost = $maid->getTotalCostOfMaid();

            $transaction->update([
                'amount' => $newCost,
                'metadata' => [
                    'maid_number' => $maid->maid_number,
                    'name' => $maid->name,
                    'cost_of_maid' => $maid->cost_of_maid,
                    'commission' => $maid->commission,
                ],
            ]);
        }
    }

    /**
     * Update receipt revenue transaction
     */
    public function updateReceiptRevenue($receipt): void
    {
        $transaction = FinancialTransaction::where('reference_type', 'App\Models\Receipt')->where('reference_id', $receipt->id)->first();

        if ($transaction) {
            $invoice = $receipt->invoice;

            $newFiscalYear = $this->getFinancialYearForDate(Carbon::parse($receipt->receipt_date));

            $transaction->update([
                'amount' => (float) $receipt->amount,
                'transaction_date' => Carbon::parse($receipt->receipt_date),
                'financial_year_id' => $newFiscalYear ? $newFiscalYear->id : $transaction->financial_year_id,
                'metadata' => [
                    'receipt_number' => $receipt->receipt_number,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_method' => $receipt->payment_method,
                    'reference' => $receipt->reference,
                ],
            ]);
        }
    }

    /**
     * Get dashboard data for admin
     */
    public function getAdminDashboardData(int $financialYearId): array
    {
        $selectedYear = FinancialYear::findOrFail($financialYearId);

        $expenses = $this->getTotalExpenses($selectedYear->id);
        $revenue = $this->getTotalRevenue($selectedYear->id);

        $previousYear = FinancialYear::where('start_date', '<', $selectedYear->start_date)->orderBy('start_date', 'desc')->first();

        $previousRevenue = $previousYear ? $this->getTotalRevenue($previousYear->id) : 0;
        $previousExpenses = $previousYear ? $this->getTotalExpenses($previousYear->id) : 0;

        $projectedRevenue = Invoice::whereHas('order.quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('invoice_date', [$selectedYear->start_date, $selectedYear->end_date])->whereNot('status', 'cancelled')->sum('amount');
        $previousProjectedRevenue = $previousYear ? Invoice::whereHas('order.quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('invoice_date', [$previousYear->start_date, $previousYear->end_date])->whereNot('status', 'cancelled')->sum('amount') : 0;

        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $previousMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $previousMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $currentMonthSales = Invoice::whereHas('order.quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('invoice_date', [$currentMonthStart, $currentMonthEnd])->whereNot('status', 'cancelled')->sum('amount');
        $previousMonthSales = Invoice::whereHas('order.quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('invoice_date', [$previousMonthStart, $previousMonthEnd])->whereNot('status', 'cancelled')->sum('amount');

        return [
            'expenses' => $expenses,
            'previous_expenses' => $previousExpenses,

            'revenue' => $revenue,
            'previous_revenue' => $previousRevenue,

            'projected_revenue' => $projectedRevenue,
            'previous_projected_revenue' => $previousProjectedRevenue,

            'current_month_sales' => $currentMonthSales,
            'previous_month_sales' => $previousMonthSales,

            'current_month_start' => $currentMonthStart,
            'current_month_end' => $currentMonthEnd,

            'selected_year' => $selectedYear,
            'previous_year' => $previousYear,
        ];
    }

    /**
     * Get available financial years for filter
     */
    public function getAvailableYears(): array
    {
        return FinancialYear::where('is_active', true)->orderBy('start_date', 'desc')->get()->map(function ($year) {
            return ['value' => $year->id, 'label' => $year->year];
        })->toArray();
    }

    /**
     * Get chart data for dashboard
     */
    public function getChartData(int $financialYearId): array
    {
        return [
            'this-year' => $this->getMonthlyBreakdown($financialYearId),
            'last-semester' => $this->getLastMonthsBreakdown(6),
            'last-quarter' => $this->getLastMonthsBreakdown(3),
        ];
    }
}
