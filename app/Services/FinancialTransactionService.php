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
                    'invoice_number' => $invoice?->invoice_number,
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

    public function getPaymentCategorySummary(
        string $period = 'daily',
        ?int $financialYearId = null,
        ?string $timezone = null,
        ?string $rangeStartUtc = null,
        ?string $rangeEndUtc = null,
    ): array {
        [$resolvedPeriod, $startDate, $endDate, $periodLabel] = $this->resolvePaymentPeriodRange(
            $period,
            $financialYearId,
            $timezone,
            $rangeStartUtc,
            $rangeEndUtc,
        );

        $receipts = Receipt::query()
            ->with([
                'invoice.quotationItems.taxes',
                'invoice.quotationItems.parent.parent',
                'invoice.order.quotation.customerConfirmation.package',
            ])
            ->where(function ($query) {
                $query->whereNull('invoice_id')
                    ->orWhereHas('invoice.order.quotation', function ($quotationQuery) {
                        $quotationQuery->whereNotIn('status', ['cancelled', 'rejected', 'expired']);
                    });
            })
            ->whereDate('receipt_date', '>=', $startDate->copy()->startOfDay()->toDateString())
            ->whereDate('receipt_date', '<=', $endDate->copy()->endOfDay()->toDateString())
            ->get();

        $categoryTotals = [];
        $bucketTotals = [];
        $reportRows = [];
        $totalAmount = 0.0;

        foreach ($receipts as $receipt) {
            $receiptAmount = (float) ($receipt->amount ?? 0);
            $totalAmount += $receiptAmount;

            $receiptDate = Carbon::parse($receipt->receipt_date);

            $bucketKey = $this->formatPaymentBucketKey($receiptDate, $resolvedPeriod);
            $bucketLabel = $this->formatPaymentBucketLabel($receiptDate, $resolvedPeriod);

            if (! isset($bucketTotals[$bucketKey])) {
                $bucketTotals[$bucketKey] = [
                    'key' => $bucketKey,
                    'label' => $bucketLabel,
                    'amount' => 0.0,
                ];
            }

            $bucketTotals[$bucketKey]['amount'] += $receiptAmount;
            $dateLabel = $this->formatPaymentBucketLabel($receiptDate, $resolvedPeriod);

            $invoice = $receipt->invoice;
            if (! $invoice) {
                $this->addCategoryAmount($categoryTotals, 'Others', $receiptAmount);

                $reportRows[] = [
                    'date' => $dateLabel,
                    'category' => 'Others',
                    'package_item' => '-',
                    'ref_no' => '',
                    ...$this->buildPaymentMethodColumns((string) ($receipt->payment_method ?? ''), $receiptAmount),
                    'total_sale' => round($receiptAmount, 2),
                    'maker' => '',
                    'remarks' => '',
                ];

                continue;
            }

            $quotation = $invoice->order?->quotation;
            $items = $invoice->quotationItems
                ->filter(fn (QuotationItem $item) => ! $item->is_header)
                ->values();

            if ($items->isEmpty()) {
                $this->addCategoryAmount($categoryTotals, 'Others', $receiptAmount);

                $reportRows[] = [
                    'date' => $dateLabel,
                    'category' => 'Others',
                    'package_item' => '-',
                    'ref_no' => '',
                    ...$this->buildPaymentMethodColumns((string) ($receipt->payment_method ?? ''), $receiptAmount),
                    'total_sale' => round($receiptAmount, 2),
                    'maker' => '',
                    'remarks' => '',
                ];

                continue;
            }

            $packageName = $quotation?->customerConfirmation?->package?->name;
            $extensionRows = collect(is_array($quotation?->extensions ?? null) ? $quotation?->extensions : []);

            $itemsWithBase = $items->map(function (QuotationItem $item): array {
                $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);

                $itemTaxAmount = collect($item->taxes ?? [])->reduce(function (float $carry, $tax) use ($lineAmount): float {
                    $mode = (string) ($tax->calculation_mode ?? '');
                    $value = (float) ($tax->calculation_value ?? 0);

                    if (! in_array($mode, ['fixed', 'percentage'], true) || $value <= 0) {
                        return $carry;
                    }

                    return $carry + ($mode === 'percentage'
                        ? ($lineAmount * $value) / 100
                        : $value);
                }, 0.0);

                return [
                    'item' => $item,
                    'base' => $lineAmount,
                    'base_with_tax' => $lineAmount + $itemTaxAmount,
                ];
            })->values();

            $subtotalAmount = (float) $itemsWithBase->sum('base');
            $subtotalWithTax = (float) $itemsWithBase->sum('base_with_tax');

            $discountExtension = $extensionRows
                ->first(function ($extension): bool {
                    return is_array($extension)
                        && (string) ($extension['type'] ?? '') === 'discount';
                });

            $nonDiscountTotal = $extensionRows
                ->filter(function ($extension): bool {
                    return is_array($extension)
                        && (string) ($extension['type'] ?? '') !== 'discount';
                })
                ->reduce(function (float $carry, $extension) use ($subtotalAmount): float {
                    if (! is_array($extension)) {
                        return $carry;
                    }

                    $mode = (string) ($extension['calculation_mode'] ?? 'fixed');
                    $value = (float) ($extension['calculation_value'] ?? $extension['amount'] ?? 0);

                    return $carry + ($mode === 'percentage'
                        ? ($subtotalAmount * $value) / 100
                        : $value);
                }, 0.0);

            $discountTotal = 0.0;
            if (is_array($discountExtension)) {
                $discountMode = (string) ($discountExtension['calculation_mode'] ?? 'fixed');
                $discountValue = abs((float) ($discountExtension['calculation_value'] ?? $discountExtension['amount'] ?? 0));
                $discountTotal = -abs($discountMode === 'percentage'
                    ? ($subtotalAmount * $discountValue) / 100
                    : $discountValue);
            }

            $itemsWithGross = $itemsWithBase->map(function (array $row) use ($subtotalAmount, $nonDiscountTotal, $discountTotal): array {
                $ratio = $subtotalAmount > 0
                    ? ((float) ($row['base'] ?? 0) / $subtotalAmount)
                    : 0;

                $gross = (float) ($row['base_with_tax'] ?? 0)
                    + ($nonDiscountTotal * $ratio)
                    + ($discountTotal * $ratio);

                return [
                    ...$row,
                    'gross' => $gross,
                ];
            })->values();

            $grossTotal = (float) $itemsWithGross->sum('gross');
            $allocated = 0.0;
            $lastIndex = max(0, $itemsWithGross->count() - 1);

            foreach ($itemsWithGross as $index => $row) {
                /** @var QuotationItem $item */
                $item = $row['item'];

                if ($index === $lastIndex) {
                    $allocatedAmount = round($receiptAmount - $allocated, 2);
                } else {
                    $ratio = $grossTotal > 0
                        ? ((float) ($row['gross'] ?? 0) / $grossTotal)
                        : (1 / max(1, $itemsWithGross->count()));
                    $allocatedAmount = round($receiptAmount * $ratio, 2);
                    $allocated += $allocatedAmount;
                }

                $category = $this->resolveItemCategoryLabel($item);
                $packageItem = $this->resolvePackageItemLabel($item, $category, $packageName);

                $this->addCategoryAmount($categoryTotals, $category, $allocatedAmount);

                $reportRows[] = [
                    'date' => $dateLabel,
                    'category' => $category,
                    'package_item' => $packageItem,
                    'ref_no' => '',
                    ...$this->buildPaymentMethodColumns((string) ($receipt->payment_method ?? ''), $allocatedAmount),
                    'total_sale' => round($allocatedAmount, 2),
                    'maker' => '',
                    'remarks' => '',
                ];
            }
        }

        $categories = collect($categoryTotals)
            ->map(function (array $row) {
                return [
                    'category' => $row['category'],
                    'amount' => round((float) $row['amount'], 2),
                    'receipt_count' => (int) $row['receipt_count'],
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();

        $buckets = collect($bucketTotals)
            ->sortBy('key')
            ->map(function (array $row) {
                return [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'amount' => round((float) $row['amount'], 2),
                ];
            })
            ->values()
            ->all();

        return [
            'period' => $resolvedPeriod,
            'period_label' => $periodLabel,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'date_range_label' => $startDate->translatedFormat('d F Y').' - '.$endDate->translatedFormat('d F Y'),
            'total_amount' => round($totalAmount, 2),
            'receipt_count' => $receipts->count(),
            'categories' => $categories,
            'buckets' => $buckets,
            'rows' => $reportRows,
        ];
    }

    private function buildPaymentMethodColumns(string $paymentMethod, float $amount): array
    {
        $normalized = strtolower(trim($paymentMethod));

        if ($normalized === 'transfer') {
            $normalized = 'nets';
        }

        $columns = [
            'amount' => round($amount, 2),
            'cash' => 0.0,
            'nets' => 0.0,
            'visa' => 0.0,
            'master' => 0.0,
            'paynow' => 0.0,
        ];

        if (array_key_exists($normalized, $columns)) {
            $columns[$normalized] = round($amount, 2);
        }

        return $columns;
    }

    private function addCategoryAmount(array &$categoryTotals, string $category, float $amount): void
    {
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

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon, 3: string}
     */
    private function resolvePaymentPeriodRange(
        string $period,
        ?int $financialYearId = null,
        ?string $timezone = null,
        ?string $rangeStartUtc = null,
        ?string $rangeEndUtc = null,
    ): array {
        $resolvedPeriod = in_array($period, ['daily', 'monthly', 'yearly'], true)
            ? $period
            : 'daily';

        $timezoneName = is_string($timezone) && trim($timezone) !== ''
            ? trim($timezone)
            : config('app.timezone', 'UTC');

        $isValidTimezone = in_array($timezoneName, timezone_identifiers_list(), true);

        if (! $isValidTimezone) {
            $timezoneName = config('app.timezone', 'UTC');
        }

        if (
            is_string($rangeStartUtc) && trim($rangeStartUtc) !== '' &&
            is_string($rangeEndUtc) && trim($rangeEndUtc) !== ''
        ) {
            try {
                $startDate = Carbon::parse($rangeStartUtc, 'UTC')
                    ->setTimezone($timezoneName)
                    ->startOfDay();
                $endDate = Carbon::parse($rangeEndUtc, 'UTC')
                    ->setTimezone($timezoneName)
                    ->endOfDay();

                return [
                    $resolvedPeriod,
                    $startDate,
                    $endDate,
                    $resolvedPeriod === 'daily'
                        ? 'Daily'
                        : ($resolvedPeriod === 'monthly' ? 'Monthly' : 'Yearly'),
                ];
            } catch (\Throwable) {
                // Fallback to default period resolution below.
            }
        }

        $now = Carbon::now($timezoneName);

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

    private function resolveItemCategoryLabel(QuotationItem $item): string
    {
        $parent = $item->parent;

        while ($parent && ! $parent->is_header) {
            $parent = $parent->parent;
        }

        if (! $parent) {
            return 'Others';
        }

        $root = $parent;

        while ($root->parent && $root->parent->is_header) {
            $root = $root->parent;
        }

        $rootDescription = trim((string) $root->description);

        if ($rootDescription === '') {
            return 'Others';
        }

        return $rootDescription;
    }

    private function resolvePackageItemLabel(QuotationItem $item, string $category, ?string $packageName): string
    {
        $description = trim((string) ($item->description ?? ''));

        if ($description === '') {
            $description = '-';
        }

        if (strtolower(trim($category)) === 'umrah packages' && is_string($packageName) && trim($packageName) !== '') {
            return trim($packageName).' - '.$description;
        }

        return $description;
    }
}
