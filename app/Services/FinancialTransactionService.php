<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        FinancialYear::progressFinancialYear();

        $resolvedYear = FinancialYear::resolveForTransactionDate($date);

        if ($resolvedYear) {
            return $resolvedYear;
        }

        return FinancialYear::getCurrentYear();
    }

    public function resolveFinancialYearForDate(Carbon $date): ?FinancialYear
    {
        return $this->getFinancialYearForDate($date);
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

        if (! $financialYear->start_date || ! $financialYear->end_date) {
            return [];
        }

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

        $currentFiscalYear = $this->resolveCurrentFiscalYear();

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

        $fiscalDayOfMonth = (int) $currentFiscalYear->start_date->day;

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

        if (! $financialYear->start_date || ! $financialYear->end_date) {
            return [];
        }

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

        $receiptsQuery = Receipt::query()
            ->with([
                'invoice.quotationItems.taxes',
                'invoice.quotationItems.parent.parent',
                'invoice.quotationItems.confirmationMember.confirmation.package',
                'invoice.order.invoices',
                'invoice.order.quotation.createdBy',
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
            ->orderBy('receipt_date')
            ->orderBy('id');

        if (DataScope::shouldScopePaymentCreatorCountry()) {
            $receiptsQuery->where(function ($query): void {
                $query->whereNull('invoice_id')
                    ->orWhereHas('invoice.order.quotation', function ($quotationQuery): void {
                        DataScope::applyPaymentCreatorCountryScopeToQuotations($quotationQuery);
                    });
            });
        }

        $receipts = $receiptsQuery->get();

        $categoryTotals = [];
        $bucketTotals = [];
        $reportRows = [];
        $rowSequence = 0;
        $paymentMethodKeys = [];
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
            $paymentMethodKey = $this->normalizePaymentMethodKey((string) ($receipt->payment_method ?? ''));

            if (! in_array($paymentMethodKey, $paymentMethodKeys, true)) {
                $paymentMethodKeys[] = $paymentMethodKey;
            }

            $invoice = $receipt->invoice;
            if (! $invoice) {
                $this->addCategoryAmount($categoryTotals, 'Others', $receiptAmount);

                $reportRows[] = [
                    'date' => $dateLabel,
                    'category' => 'Others',
                    'package_item' => '-',
                    'ref_no' => '',
                    'payment_method' => $paymentMethodKey,
                    '__bucket_key' => $bucketKey,
                    '__row_sequence' => ++$rowSequence,
                    'amount' => round($receiptAmount, 2),
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
                    'payment_method' => $paymentMethodKey,
                    '__bucket_key' => $bucketKey,
                    '__row_sequence' => ++$rowSequence,
                    'amount' => round($receiptAmount, 2),
                    'total_sale' => round($receiptAmount, 2),
                    'maker' => (string) ($quotation?->createdBy?->name ?? ''),
                    'remarks' => $this->resolveReceiptRemark($invoice),
                ];

                continue;
            }

            $maker = (string) ($quotation?->createdBy?->name ?? '');
            $remarks = $this->resolveReceiptRemark($invoice);

            $itemsWithBase = $items->map(function (QuotationItem $item): array {
                $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);

                $itemTaxAmount = collect($item->taxes ?? [])->reduce(function (float $carry, $tax) use ($lineAmount): float {
                    $mode = (string) ($tax->calculation_mode ?? '');
                    $value = (float) ($tax->calculation_value ?? 0);

                    if (! in_array($mode, ['fixed', 'percentage'], true) || $value === 0.0) {
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

            $payerItemId = $this->resolvePayerItemId(
                $items,
                (int) ($quotation?->customer_id ?? 0),
            );
            $negativeInvoiceExtensionTotal = $this->resolveNegativeInvoiceExtensionTotal($invoice);

            $itemsWithGross = $itemsWithBase->map(function (array $row) use ($payerItemId, $negativeInvoiceExtensionTotal): array {
                /** @var QuotationItem $item */
                $item = $row['item'];
                $itemId = (int) ($item->id ?? 0);

                $gross = (float) ($row['base_with_tax'] ?? 0);

                if ($payerItemId !== null && $itemId === $payerItemId) {
                    $gross += $negativeInvoiceExtensionTotal;
                }

                $gross = max($gross, 0);

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
                $packageItem = $this->resolvePackageItemLabel($item);
                $referenceNumber = $this->resolveReferenceNumber($item);

                $this->addCategoryAmount($categoryTotals, $category, $allocatedAmount);

                $reportRows[] = [
                    'date' => $dateLabel,
                    'category' => $category,
                    'package_item' => $packageItem,
                    'ref_no' => $referenceNumber,
                    'payment_method' => $paymentMethodKey,
                    '__bucket_key' => $bucketKey,
                    '__row_sequence' => ++$rowSequence,
                    'amount' => round($allocatedAmount, 2),
                    'total_sale' => round($allocatedAmount, 2),
                    'maker' => $maker,
                    'remarks' => $remarks,
                ];
            }
        }

        $defaultPaymentMethods = ['cash', 'nets', 'visa', 'master', 'paynow'];
        $extraPaymentMethods = collect($paymentMethodKeys)
            ->filter(fn (string $method): bool => ! in_array($method, $defaultPaymentMethods, true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $paymentMethods = array_values(array_unique(array_merge($defaultPaymentMethods, $extraPaymentMethods)));

        $reportRows = collect($reportRows)
            ->sortBy([
                ['__bucket_key', 'asc'],
                ['__row_sequence', 'asc'],
            ])
            ->values()
            ->map(function (array $row) use ($paymentMethods): array {
                $resolvedRow = $row;
                $resolvedAmount = (float) ($resolvedRow['amount'] ?? 0);
                $resolvedMethod = (string) ($resolvedRow['payment_method'] ?? 'others');

                foreach ($paymentMethods as $method) {
                    $resolvedRow[$method] = 0.0;
                }

                if (array_key_exists($resolvedMethod, $resolvedRow)) {
                    $resolvedRow[$resolvedMethod] = round($resolvedAmount, 2);
                }

                unset($resolvedRow['payment_method'], $resolvedRow['__bucket_key'], $resolvedRow['__row_sequence']);

                return $resolvedRow;
            })
            ->all();

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
            'payment_methods' => $paymentMethods,
            'rows' => $reportRows,
        ];
    }

    private function resolveCurrentFiscalYear(): ?FinancialYear
    {
        return FinancialYear::query()
            ->where('is_active', true)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->orderByDesc('default')
            ->orderByDesc('start_date')
            ->first();
    }

    private function normalizePaymentMethodKey(string $paymentMethod): string
    {
        $normalized = strtolower(trim($paymentMethod));
        $collapsed = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';

        if ($collapsed === '') {
            return 'others';
        }

        return match ($collapsed) {
            'transfer', 'banktransfer', 'nets' => 'nets',
            'cash' => 'cash',
            'visa' => 'visa',
            'master', 'mastercard', 'mastercarddebit', 'mastercardcredit' => 'master',
            'paynow', 'paylah' => 'paynow',
            default => Str::snake($normalized),
        };
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
     * @param  Collection<int, QuotationItem>  $items
     */
    private function resolvePayerItemId(Collection $items, int $quotationCustomerId): ?int
    {
        $payerItem = $items->first(function (QuotationItem $item) use ($quotationCustomerId): bool {
            if ($quotationCustomerId <= 0) {
                return false;
            }

            return (int) ($item->confirmationMember?->customer_id ?? 0) === $quotationCustomerId;
        });

        if (! $payerItem instanceof QuotationItem) {
            $payerItem = $items->first();
        }

        if (! $payerItem instanceof QuotationItem) {
            return null;
        }

        $payerItemId = (int) ($payerItem->id ?? 0);

        return $payerItemId > 0 ? $payerItemId : null;
    }

    private function resolveNegativeInvoiceExtensionTotal(Invoice $invoice): float
    {
        $extensions = is_array($invoice->extensions) ? $invoice->extensions : [];

        return (float) collect($extensions)->sum(function ($extension): float {
            if (! is_array($extension)) {
                return 0.0;
            }

            $amount = (float) ($extension['amount'] ?? 0);

            if ($amount < 0) {
                return $amount;
            }

            $type = strtolower(trim((string) ($extension['type'] ?? '')));

            if ($type === 'discount' && $amount > 0) {
                return -abs($amount);
            }

            return 0.0;
        });
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

    private function resolvePackageItemLabel(QuotationItem $item): string
    {
        $description = trim((string) ($item->description ?? ''));

        if ($description === '') {
            $description = '-';
        }

        return $description;
    }

    private function resolveReferenceNumber(QuotationItem $item): string
    {
        return (string) ($item->confirmationMember?->confirmation?->package?->package_number ?? '');
    }

    private function resolveReceiptRemark(Invoice $invoice): string
    {
        $order = $invoice->order;

        if (! $order) {
            return '';
        }

        $paymentPlan = strtolower((string) ($order->payment_plan ?? $order->quotation?->payment_plan ?? ''));

        $orderedInvoices = $order->invoices
            ->sortBy(function (Invoice $orderedInvoice): array {
                return [
                    (int) ($orderedInvoice->created_at?->getTimestamp() ?? 0),
                    (int) ($orderedInvoice->id ?? 0),
                ];
            })
            ->values();

        if ($orderedInvoices->count() <= 1 || in_array($paymentPlan, ['full', 'direct'], true)) {
            return 'Full Payment';
        }

        $index = $orderedInvoices->search(fn (Invoice $candidate): bool => (int) $candidate->id === (int) $invoice->id);
        $position = is_int($index) ? $index + 1 : 1;

        return $this->ordinalPaymentLabel($position);
    }

    private function ordinalPaymentLabel(int $position): string
    {
        $labels = [
            1 => 'First',
            2 => 'Second',
            3 => 'Third',
            4 => 'Fourth',
            5 => 'Fifth',
            6 => 'Sixth',
            7 => 'Seventh',
            8 => 'Eighth',
            9 => 'Ninth',
            10 => 'Tenth',
        ];

        if (array_key_exists($position, $labels)) {
            return $labels[$position].' Payment';
        }

        return $position.'th Payment';
    }

    /**
     * Get package group payment summary (aggregated per date, not per item).
     * Used for the Closing Report / Package Group Report on the dashboard.
     *
     * When $packageId is null → include all receipts (no package filter).
     * When $packageId is set  → only receipts linked to that package via
     *   invoice → order → quotation → customerConfirmation → package_id.
     */
    public function getPackageGroupPaymentSummary(
        string $period = 'monthly',
        ?int $financialYearId = null,
        ?string $timezone = null,
        ?string $rangeStartUtc = null,
        ?string $rangeEndUtc = null,
        ?int $packageId = null,
    ): array {
        [$resolvedPeriod, $startDate, $endDate, $periodLabel] = $this->resolvePaymentPeriodRange(
            $period,
            $financialYearId,
            $timezone,
            $rangeStartUtc,
            $rangeEndUtc,
        );

        $receiptsQuery = Receipt::query()
            ->with([
                'invoice.quotationItems.taxes',
                'invoice.quotationItems.parent.parent',
                'invoice.quotationItems.confirmationMember.confirmation.package',
                'invoice.order.invoices',
                'invoice.order.quotation.createdBy',
                'invoice.order.quotation.customerConfirmation.package',
            ])
            ->whereDate('receipt_date', '>=', $startDate->copy()->startOfDay()->toDateString())
            ->whereDate('receipt_date', '<=', $endDate->copy()->endOfDay()->toDateString())
            ->orderBy('receipt_date')
            ->orderBy('id');

        if ($packageId !== null) {
            // Package filter: only receipts whose quotation links to this package
            $receiptsQuery->whereHas('invoice.order.quotation', function ($q) use ($packageId): void {
                $q->whereNotIn('status', ['cancelled', 'rejected', 'expired'])
                    ->whereHas('customerConfirmation', function ($ccq) use ($packageId): void {
                        $ccq->where('package_id', $packageId);
                    });
            });
        } else {
            // No package filter — include all receipts (standalone + invoiced non-cancelled)
            $receiptsQuery->where(function ($query): void {
                $query->whereNull('invoice_id')
                    ->orWhereHas('invoice.order.quotation', function ($quotationQuery): void {
                        $quotationQuery->whereNotIn('status', ['cancelled', 'rejected', 'expired']);
                    });
            });
        }

        if (DataScope::shouldScopePaymentCreatorCountry()) {
            $receiptsQuery->where(function ($query): void {
                $query->whereNull('invoice_id')
                    ->orWhereHas('invoice.order.quotation', function ($quotationQuery): void {
                        DataScope::applyPaymentCreatorCountryScopeToQuotations($quotationQuery);
                    });
            });
        }

        $receipts = $receiptsQuery->get();

        // Per-date accumulator: [dateKey => [date, day_name, categories[], payment_methods[], total_sales]]
        $dateBuckets = [];
        $allCategoryKeys = [];   // [catKey => catLabel]
        $allMethodKeys = [];     // [methodKey => true]
        $totalAmount = 0.0;

        $defaultPaymentMethods = ['cash', 'nets', 'visa', 'master', 'paynow'];
        foreach ($defaultPaymentMethods as $m) {
            $allMethodKeys[$m] = true;
        }

        foreach ($receipts as $receipt) {
            $receiptAmount = (float) ($receipt->amount ?? 0);
            $totalAmount += $receiptAmount;

            $receiptDate = Carbon::parse($receipt->receipt_date);
            $dateKey = $receiptDate->format('Y-m-d');
            $dateLabel = $receiptDate->translatedFormat('d F Y');
            $dayName = $receiptDate->translatedFormat('l');
            $paymentMethodKey = $this->normalizePaymentMethodKey((string) ($receipt->payment_method ?? ''));
            $allMethodKeys[$paymentMethodKey] = true;

            if (! isset($dateBuckets[$dateKey])) {
                $dateBuckets[$dateKey] = [
                    'date_sort'       => $dateKey,
                    'date'            => $dateLabel,
                    'day_name'        => $dayName,
                    'categories'      => [],
                    'payment_methods' => [],
                    'total_sales'     => 0.0,
                ];
            }

            // Accumulate payment method total for the date
            if (! isset($dateBuckets[$dateKey]['payment_methods'][$paymentMethodKey])) {
                $dateBuckets[$dateKey]['payment_methods'][$paymentMethodKey] = 0.0;
            }
            $dateBuckets[$dateKey]['payment_methods'][$paymentMethodKey] += $receiptAmount;
            $dateBuckets[$dateKey]['total_sales'] += $receiptAmount;

            // Distribute receipt amount across categories (ratio-based, same as existing report)
            $invoice = $receipt->invoice;

            if (! $invoice) {
                $this->addDateCategoryAmount($dateBuckets, $dateKey, 'Others');
                $this->addDateCategoryAmountValue($dateBuckets, $dateKey, 'Others', $receiptAmount);
                $allCategoryKeys[$this->toCategoryKey('Others')] = 'Others';
                continue;
            }

            $quotation = $invoice->order?->quotation;
            $items = $invoice->quotationItems
                ->filter(fn (QuotationItem $item) => ! $item->is_header)
                ->values();

            if ($items->isEmpty()) {
                $this->addDateCategoryAmount($dateBuckets, $dateKey, 'Others');
                $this->addDateCategoryAmountValue($dateBuckets, $dateKey, 'Others', $receiptAmount);
                $allCategoryKeys[$this->toCategoryKey('Others')] = 'Others';
                continue;
            }

            // Build weighted gross amounts per item (same ratio logic as getPaymentCategorySummary)
            $itemsWithBase = $items->map(function (QuotationItem $item): array {
                $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                $itemTaxAmount = collect($item->taxes ?? [])->reduce(function (float $carry, $tax) use ($lineAmount): float {
                    $mode = (string) ($tax->calculation_mode ?? '');
                    $value = (float) ($tax->calculation_value ?? 0);
                    if (! in_array($mode, ['fixed', 'percentage'], true) || $value === 0.0) {
                        return $carry;
                    }

                    return $carry + ($mode === 'percentage' ? ($lineAmount * $value) / 100 : $value);
                }, 0.0);

                return ['item' => $item, 'base' => $lineAmount, 'base_with_tax' => $lineAmount + $itemTaxAmount];
            })->values();

            $payerItemId = $this->resolvePayerItemId($items, (int) ($quotation?->customer_id ?? 0));
            $negativeInvoiceExtensionTotal = $this->resolveNegativeInvoiceExtensionTotal($invoice);

            $itemsWithGross = $itemsWithBase->map(function (array $row) use ($payerItemId, $negativeInvoiceExtensionTotal): array {
                /** @var QuotationItem $item */
                $item = $row['item'];
                $gross = (float) ($row['base_with_tax'] ?? 0);
                if ($payerItemId !== null && (int) ($item->id ?? 0) === $payerItemId) {
                    $gross += $negativeInvoiceExtensionTotal;
                }

                return [...$row, 'gross' => max($gross, 0)];
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

                $categoryLabel = $this->resolveItemCategoryLabel($item);
                $catKey = $this->toCategoryKey($categoryLabel);

                $this->addDateCategoryAmount($dateBuckets, $dateKey, $categoryLabel);
                $this->addDateCategoryAmountValue($dateBuckets, $dateKey, $categoryLabel, $allocatedAmount);
                $allCategoryKeys[$catKey] = $categoryLabel;
            }
        }

        // Sort date buckets chronologically
        ksort($dateBuckets);

        // Build ordered payment methods list
        $extraPaymentMethods = collect(array_keys($allMethodKeys))
            ->filter(fn (string $m): bool => ! in_array($m, $defaultPaymentMethods, true))
            ->sort()
            ->values()
            ->all();
        $paymentMethods = array_values(array_unique(array_merge($defaultPaymentMethods, $extraPaymentMethods)));

        // Build flat rows with all category and payment-method keys
        $rows = [];
        foreach ($dateBuckets as $bucket) {
            $row = [
                'date_sort'   => $bucket['date_sort'],
                'date'        => $bucket['date'],
                'day_name'    => $bucket['day_name'],
                'total_sales' => round($bucket['total_sales'], 2),
            ];

            foreach (array_keys($allCategoryKeys) as $catKey) {
                $row[$catKey] = round($bucket['categories'][$catKey] ?? 0.0, 2);
            }

            foreach ($paymentMethods as $methodKey) {
                $row[$methodKey] = round($bucket['payment_methods'][$methodKey] ?? 0.0, 2);
            }

            $rows[] = $row;
        }

        // Resolve package info for display in the report
        $packageInfo = null;
        if ($packageId !== null) {
            $pkg = \App\Models\Package::find($packageId);
            if ($pkg) {
                $packageInfo = [
                    'id'             => $pkg->id,
                    'package_number' => $pkg->package_number,
                    'name'           => $pkg->name,
                ];
            }
        }

        return [
            'mode'             => $resolvedPeriod,
            'period_label'     => $periodLabel,
            'start_date'       => $startDate->toDateString(),
            'end_date'         => $endDate->toDateString(),
            'date_range_label' => $startDate->translatedFormat('d F Y').' - '.$endDate->translatedFormat('d F Y'),
            'total_amount'     => round($totalAmount, 2),
            'receipt_count'    => $receipts->count(),
            'package'          => $packageInfo,
            'categories'       => $allCategoryKeys,   // [catKey => catLabel]
            'payment_methods'  => $paymentMethods,    // [methodKey, ...]
            'rows'             => $rows,
        ];
    }

    /**
     * Initialise a category slot in the date bucket if it does not exist yet.
     */
    private function addDateCategoryAmount(array &$dateBuckets, string $dateKey, string $categoryLabel): void
    {
        $catKey = $this->toCategoryKey($categoryLabel);
        if (! isset($dateBuckets[$dateKey]['categories'][$catKey])) {
            $dateBuckets[$dateKey]['categories'][$catKey] = 0.0;
        }
    }

    /**
     * Accumulate an amount into a date-bucket's category total.
     */
    private function addDateCategoryAmountValue(array &$dateBuckets, string $dateKey, string $categoryLabel, float $amount): void
    {
        $catKey = $this->toCategoryKey($categoryLabel);
        $dateBuckets[$dateKey]['categories'][$catKey] = ($dateBuckets[$dateKey]['categories'][$catKey] ?? 0.0) + $amount;
    }

    /**
     * Convert a human-readable category label to a snake_case key.
     */
    private function toCategoryKey(string $label): string
    {
        $key = Str::slug(trim($label), '_');

        return $key !== '' ? $key : 'others';
    }
}
