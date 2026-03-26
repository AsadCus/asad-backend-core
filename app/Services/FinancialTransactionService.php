<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\QuotationItem;
use App\Models\Receipt;
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

    public function getPaymentCategorySummary(string $period = 'daily', ?int $financialYearId = null): array
    {
        [$resolvedPeriod, $startDate, $endDate, $periodLabel] = $this->resolvePaymentPeriodRange($period, $financialYearId);

        $receipts = Receipt::query()
            ->with([
                'invoice.quotationItems.parent.parent',
            ])
            ->whereBetween('receipt_date', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->orderBy('receipt_date', 'asc')
            ->get();

        $groups = match ($resolvedPeriod) {
            'monthly' => $this->buildMonthlyPaymentGroups($receipts->all()),
            'yearly' => $this->buildYearlyPaymentGroups($receipts->all()),
            default => $this->buildDailyPaymentGroups($receipts->all()),
        };

        return [
            'mode' => $resolvedPeriod,
            'period_label' => $periodLabel,
            'generated_at' => now()->translatedFormat('d M Y, H:i'),
            'generated_by' => auth()->user()?->name ?? 'System',
            'groups' => $groups,
        ];
    }

    /**
     * @param  array<int, Receipt>  $receipts
     * @return array<int, array<string, mixed>>
     */
    private function buildDailyPaymentGroups(array $receipts): array
    {
        $groups = [];

        foreach ($receipts as $receipt) {
            $receiptDate = Carbon::parse($receipt->receipt_date);
            $dateKey = $receiptDate->format('Y-m-d');

            if (! isset($groups[$dateKey])) {
                $groups[$dateKey] = [
                    'label' => $receiptDate->translatedFormat('d M Y'),
                    'day_name' => $receiptDate->translatedFormat('l'),
                    'rows' => [],
                ];
            }

            $groups[$dateKey]['rows'][] = $this->buildTransactionRow($receipt);
        }

        return array_values($groups);
    }

    /**
     * @param  array<int, Receipt>  $receipts
     * @return array<int, array<string, mixed>>
     */
    private function buildMonthlyPaymentGroups(array $receipts): array
    {
        $groups = [];

        foreach ($receipts as $receipt) {
            $receiptDate = Carbon::parse($receipt->receipt_date);
            $monthKey = $receiptDate->format('Y-m');
            $weekNumber = (int) ceil($receiptDate->day / 7);
            $weekKey = $monthKey.'-W'.$weekNumber;

            if (! isset($groups[$monthKey])) {
                $groups[$monthKey] = [
                    'label' => $receiptDate->translatedFormat('F Y'),
                    'sub_periods' => [],
                ];
            }

            if (! isset($groups[$monthKey]['sub_periods'][$weekKey])) {
                $groups[$monthKey]['sub_periods'][$weekKey] = [
                    'label' => 'Week '.$weekNumber,
                    'receipts' => [],
                ];
            }

            $groups[$monthKey]['sub_periods'][$weekKey]['receipts'][] = $receipt;
        }

        foreach ($groups as &$group) {
            $subPeriods = $group['sub_periods'] ?? [];
            ksort($subPeriods);

            $group['sub_periods'] = array_values(array_map(function (array $subPeriod): array {
                return [
                    'label' => $subPeriod['label'] ?? '-',
                    'rows' => $this->buildAggregatedRows($subPeriod['receipts'] ?? []),
                ];
            }, $subPeriods));
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param  array<int, Receipt>  $receipts
     * @return array<int, array<string, mixed>>
     */
    private function buildYearlyPaymentGroups(array $receipts): array
    {
        $groups = [];

        foreach ($receipts as $receipt) {
            $receiptDate = Carbon::parse($receipt->receipt_date);
            $yearKey = $receiptDate->format('Y');
            $monthKey = $receiptDate->format('Y-m');

            if (! isset($groups[$yearKey])) {
                $groups[$yearKey] = [
                    'label' => $yearKey,
                    'sub_periods' => [],
                ];
            }

            if (! isset($groups[$yearKey]['sub_periods'][$monthKey])) {
                $groups[$yearKey]['sub_periods'][$monthKey] = [
                    'label' => $receiptDate->translatedFormat('F'),
                    'receipts' => [],
                ];
            }

            $groups[$yearKey]['sub_periods'][$monthKey]['receipts'][] = $receipt;
        }

        foreach ($groups as &$group) {
            $subPeriods = $group['sub_periods'] ?? [];
            ksort($subPeriods);

            $group['sub_periods'] = array_values(array_map(function (array $subPeriod): array {
                return [
                    'label' => $subPeriod['label'] ?? '-',
                    'rows' => $this->buildAggregatedRows($subPeriod['receipts'] ?? []),
                ];
            }, $subPeriods));
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * Build a single transaction row from a receipt
     */
    private function buildTransactionRow(Receipt $receipt): array
    {
        $receiptAmount = (float) ($receipt->amount ?? 0);
        $invoice = $receipt->invoice;

        // Determine category and package item
        $category = 'others';
        $packageItem = '-';
        $refNo = $invoice?->invoice_number ?? '-';

        if ($invoice) {
            $items = $invoice->quotationItems
                ->filter(fn (QuotationItem $item) => ! $item->is_header)
                ->values();

            if ($items->isNotEmpty()) {
                $firstItem = $items->first();
                $category = $this->resolveCategoryKey($firstItem);
                $packageItem = trim((string) $firstItem->description) ?: $firstItem->parent?->description ?: '-';
            }
        }

        // Map payment method to breakdown fields
        $breakdown = $this->mapPaymentMethodToBreakdown($receipt->payment_method, $receiptAmount);

        return [
            'category' => $category,
            'package_item' => $packageItem,
            'ref_no' => $refNo,
            'amount' => round($receiptAmount, 2),
            'cash' => $breakdown['cash'],
            'nets' => $breakdown['nets'],
            'visa' => $breakdown['visa'],
            'master' => $breakdown['master'],
            'paynow' => $breakdown['paynow'],
            'total_sale' => round($receiptAmount, 2),
            'maker' => null, // Not available in current system
            'remarks' => null, // Not available in current system
        ];
    }

    /**
     * Build aggregated rows from multiple receipts (for monthly/yearly)
     */
    private function buildAggregatedRows(array $receipts): array
    {
        $rowsByCategory = [];

        foreach ($receipts as $receipt) {
            $receiptAmount = (float) ($receipt->amount ?? 0);
            $invoice = $receipt->invoice;
            $category = 'others';

            if ($invoice) {
                $items = $invoice->quotationItems
                    ->filter(fn (QuotationItem $item) => ! $item->is_header)
                    ->values();

                if ($items->isNotEmpty()) {
                    $category = $this->resolveCategoryKey($items->first());
                }
            }

            if (! isset($rowsByCategory[$category])) {
                $rowsByCategory[$category] = [
                    'category' => $category,
                    'package_item' => '-',
                    'ref_no' => '-',
                    'amount' => 0.0,
                    'cash' => 0.0,
                    'nets' => 0.0,
                    'visa' => 0.0,
                    'master' => 0.0,
                    'paynow' => 0.0,
                    'total_sale' => 0.0,
                ];
            }

            // Aggregate amount and payment method breakdown
            $breakdown = $this->mapPaymentMethodToBreakdown($receipt->payment_method, $receiptAmount);
            $rowsByCategory[$category]['amount'] += round($receiptAmount, 2);
            $rowsByCategory[$category]['cash'] += $breakdown['cash'];
            $rowsByCategory[$category]['nets'] += $breakdown['nets'];
            $rowsByCategory[$category]['visa'] += $breakdown['visa'];
            $rowsByCategory[$category]['master'] += $breakdown['master'];
            $rowsByCategory[$category]['paynow'] += $breakdown['paynow'];
            $rowsByCategory[$category]['total_sale'] += round($receiptAmount, 2);
        }

        return array_values($rowsByCategory);
    }

    /**
     * Map payment method string to breakdown fields
     */
    private function mapPaymentMethodToBreakdown(string $paymentMethod, float $amount): array
    {
        $breakdown = [
            'cash' => 0.0,
            'nets' => 0.0,
            'visa' => 0.0,
            'master' => 0.0,
            'paynow' => 0.0,
        ];

        $method = strtolower(trim($paymentMethod));

        if (str_contains($method, 'cash')) {
            $breakdown['cash'] = round($amount, 2);
        } elseif (str_contains($method, 'visa')) {
            $breakdown['visa'] = round($amount, 2);
        } elseif (str_contains($method, 'master')) {
            $breakdown['master'] = round($amount, 2);
        } elseif (str_contains($method, 'nets')) {
            $breakdown['nets'] = round($amount, 2);
        } elseif (str_contains($method, 'paynow') || str_contains($method, 'transfer')) {
            $breakdown['paynow'] = round($amount, 2);
        }

        return $breakdown;
    }

    /**
     * Resolve category key for display
     */
    private function resolveCategoryKey(QuotationItem $item): string
    {
        $parent = $item->parent;

        while ($parent && ! $parent->is_header) {
            $parent = $parent->parent;
        }

        if (! $parent) {
            return 'others';
        }

        $root = $parent;
        while ($root->parent && $root->parent->is_header) {
            $root = $root->parent;
        }

        $description = strtolower(trim((string) $root->description));

        return match (true) {
            str_contains($description, 'umrah') => 'umrah_packages',
            str_contains($description, 'leisure') => 'leisure_package',
            str_contains($description, 'friday') || str_contains($description, 'badal') => 'friday_blessings_badal',
            str_contains($description, 'wakaf') => 'wakaf_jemaah',
            default => 'others',
        };
    }

    private function addCategoryAmount(array &$categoryTotals, string $category, float $amount): void
    {
        // Deprecated - kept for backward compatibility if needed elsewhere
        $normalizedCategory = trim($category) !== '' ? trim($category) : 'Others';

        if (! isset($categoryTotals[$normalizedCategory])) {
            $categoryTotals[$normalizedCategory] = [
                'category' => $normalizedCategory,
                'amount' => 0.0,
                'receipt_count' => 0,
            ];
        }

        $categoryTotals[$normalizedCategory]['amount'] += $amount;
        $categoryTotals[$normalizedCategory]['receipt_count']++;
    }

    private function addCategoryBucketAmount(array &$categoryBucketAmounts, string $category, string $bucketKey, float $amount): void
    {
        // Deprecated - kept for backward compatibility if needed elsewhere
        $normalizedCategory = trim($category) !== '' ? trim($category) : 'Others';

        if (! isset($categoryBucketAmounts[$normalizedCategory])) {
            $categoryBucketAmounts[$normalizedCategory] = [];
        }

        if (! isset($categoryBucketAmounts[$normalizedCategory][$bucketKey])) {
            $categoryBucketAmounts[$normalizedCategory][$bucketKey] = 0.0;
        }

        $categoryBucketAmounts[$normalizedCategory][$bucketKey] += $amount;
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon, 3: string}
     */
    private function resolvePaymentPeriodRange(string $period, ?int $financialYearId = null): array
    {
        $resolvedPeriod = in_array($period, ['daily', 'monthly', 'yearly'], true)
            ? $period
            : 'daily';

        $now = Carbon::now();

        if ($resolvedPeriod === 'yearly') {
            if ($financialYearId) {
                $financialYear = FinancialYear::find($financialYearId);
                if ($financialYear) {
                    return [
                        $resolvedPeriod,
                        Carbon::parse($financialYear->start_date)->startOfDay(),
                        Carbon::parse($financialYear->end_date)->endOfDay(),
                        'Yearly',
                    ];
                }
            }

            return [
                $resolvedPeriod,
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                'Yearly',
            ];
        }

        if ($resolvedPeriod === 'monthly') {
            return [
                $resolvedPeriod,
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'Monthly',
            ];
        }

        return [
            $resolvedPeriod,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'Daily',
        ];
    }

    private function formatPaymentBucketKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'yearly' => $date->format('Y'),
            'monthly' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    private function formatPaymentBucketLabel(Carbon $date, string $period): string
    {
        return match ($period) {
            'yearly' => $date->format('Y'),
            'monthly' => $date->translatedFormat('F Y'),
            default => $date->translatedFormat('d F Y'),
        };
    }

    /**
     * Convert the new periods structure to the old categories/buckets structure
     * Used for backward compatibility with dashboard display
     */
    public function transformToLegacyFormat(array $newReport): array
    {
        if (! isset($newReport['groups']) && ! isset($newReport['periods'])) {
            return $newReport;
        }

        $mode = (string) ($newReport['mode'] ?? 'daily');
        $categories = [];
        $buckets = [];
        $totalAmount = 0.0;
        $receiptCount = 0;
        $allDates = [];

        $groups = $newReport['groups'] ?? [];

        // Backward compatibility if old format still passed in.
        if (empty($groups) && isset($newReport['periods'])) {
            $groups = array_map(static fn (array $period): array => [
                'label' => $period['label'] ?? '-',
                'rows' => $period['rows'] ?? [],
            ], $newReport['periods']);
        }

        $appendRowsToTotals = function (array $rows, string $bucketLabel) use (&$categories, &$buckets, &$totalAmount, &$receiptCount, &$allDates): void {
            $bucketKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $bucketLabel));
            $bucketKey = trim($bucketKey, '_');
            $bucketKey = $bucketKey !== '' ? $bucketKey : 'bucket';

            $allDates[] = $bucketLabel;

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'key' => $bucketKey,
                    'label' => $bucketLabel,
                    'amount' => 0.0,
                ];
            }

            foreach ($rows as $row) {
                $category = $row['category'] ?? 'others';
                $amount = (float) ($row['amount'] ?? 0);

                if (! isset($categories[$category])) {
                    $categories[$category] = [
                        'category' => $category,
                        'amount' => 0.0,
                        'receipt_count' => 0,
                    ];
                }

                $categories[$category]['amount'] += $amount;
                $categories[$category]['receipt_count'] += 1;
                $buckets[$bucketKey]['amount'] += $amount;
                $totalAmount += $amount;
                $receiptCount += 1;
            }
        };

        foreach ($groups as $group) {
            if ($mode === 'daily') {
                $appendRowsToTotals($group['rows'] ?? [], (string) ($group['label'] ?? '-'));

                continue;
            }

            $subPeriods = $group['sub_periods'] ?? [];

            foreach ($subPeriods as $subPeriod) {
                $bucketLabel = trim((string) (($group['label'] ?? '').' '.($subPeriod['label'] ?? '')));
                $bucketLabel = $bucketLabel !== '' ? $bucketLabel : '-';

                $appendRowsToTotals($subPeriod['rows'] ?? [], $bucketLabel);
            }
        }

        // Sort categories by amount descending
        usort($categories, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        // Build date range label
        $dateRangeLabel = ! empty($allDates)
            ? ($allDates[0] === end($allDates)
                ? $allDates[0]
                : $allDates[0].' - '.end($allDates))
            : '';

        return [
            'period' => $mode,
            'period_label' => $newReport['period_label'] ?? '',
            'start_date' => ! empty($allDates) ? $allDates[0] : '',
            'end_date' => ! empty($allDates) ? end($allDates) : '',
            'date_range_label' => $dateRangeLabel,
            'total_amount' => round($totalAmount, 2),
            'receipt_count' => $receiptCount,
            'categories' => array_values($categories),
            'buckets' => array_values($buckets),
        ];
    }
}
