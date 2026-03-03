<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use Carbon\Carbon;

class SalesService
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    public function getForDataTable()
    {
        return User::role('sales')->with('sales')->get()->map(function ($q) {
            return [
                'id' => $q->id,
                'name' => $q->name,
                'email' => $q->email,
                'contact' => $q->contact,
                'branch_id' => $q->sales->branch_id,
                'branch_name' => $q->sales->branch->name,
                'registration_number' => $q->sales->registration_number,
            ];
        });
    }

    public function getForFilter()
    {
        return User::role(['admin', 'sales'])->where('deleted_at', null)->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->name,
                'branch_id' => $q->sales->branch_id ?? null,
            ];
        });
    }

    public function show($id)
    {
        return User::role('sales')->withTrashed()->with('sales')->find($id);
    }

    /**
     * Get period options for sales dashboard (fiscal year + 12 months)
     */
    public function getSalesPeriodOptions(?FinancialYear $fiscalYear = null): array
    {
        $currentFiscalYear = $fiscalYear ?? FinancialYear::getCurrentYear();

        if (! $currentFiscalYear) {
            return [];
        }

        $fiscalYearStart = Carbon::parse($currentFiscalYear->start_date);
        $fiscalYearEnd = Carbon::parse($currentFiscalYear->end_date);
        $now = Carbon::now();

        $options = [];

        // Option 1: Full fiscal year
        $options[] = [
            'value' => 'full-year',
            'label' => $currentFiscalYear->year,
            'start_date' => $fiscalYearStart->format('Y-m-d'),
            'end_date' => $fiscalYearEnd->format('Y-m-d'),
        ];

        // Options 2-13: Monthly periods
        $currentPeriodStart = $fiscalYearStart->copy();
        $defaultPeriod = 'full-year';

        for ($i = 0; $i < 12; $i++) {
            $periodEnd = $currentPeriodStart->copy()->addMonth()->subDay();

            if ($periodEnd->greaterThan($fiscalYearEnd)) {
                $periodEnd = $fiscalYearEnd->copy();
            }

            // Generate label
            if ($fiscalYearStart->day === 1) {
                $label = $currentPeriodStart->translatedFormat('F');
            } else {
                $label = $currentPeriodStart->translatedFormat('d F').' - '.$periodEnd->translatedFormat('d F');
            }

            $periodValue = 'month-'.$i;

            if ($now->between($currentPeriodStart, $periodEnd)) {
                $defaultPeriod = $periodValue;
            }

            $options[] = [
                'value' => $periodValue,
                'label' => $label,
                'start_date' => $currentPeriodStart->format('Y-m-d'),
                'end_date' => $periodEnd->format('Y-m-d'),
            ];

            $currentPeriodStart->addMonth();

            if ($currentPeriodStart->greaterThan($fiscalYearEnd)) {
                break;
            }
        }

        return [
            'options' => $options,
            'default' => $defaultPeriod,
        ];
    }

    public function getQuotationConvertedBySalesperson(?string $period = 'full-year', ?FinancialYear $fiscalYear = null): array
    {
        $currentFiscalYear = $fiscalYear ?? FinancialYear::getCurrentYear();

        if (! $currentFiscalYear) {
            return [];
        }

        $periodOptions = $this->getSalesPeriodOptions($currentFiscalYear);
        $selectedPeriod = collect($periodOptions['options'])->firstWhere('value', $period);

        if (! $selectedPeriod) {
            $selectedPeriod = $periodOptions['options'][0];
        }

        $startDate = Carbon::parse($selectedPeriod['start_date']);
        $endDate = Carbon::parse($selectedPeriod['end_date']);

        return $this->getForDataTable()->map(function ($sales) use ($startDate, $endDate) {
            $convertedQuotation = Order::with('quotation')->whereHas('quotation', function ($q) use ($sales) {
                $q->whereNot('status', 'cancelled')->whereHas('customer', function ($c) use ($sales) {
                    $c->where('handled_by', $sales['id']);
                });
            })->whereBetween('created_at', [$startDate, $endDate])->get();

            $convertedQuotationCount = $convertedQuotation->count();
            $convertedQuotationAmount = 0;

            foreach ($convertedQuotation as $q) {
                $convertedQuotationAmount += $q->quotation->total_amount;
            }

            return [
                'id' => $sales['id'],
                'name' => $sales['name'],
                'email' => $sales['email'],
                'branch_name' => $sales['branch_name'],
                'converted_quotation' => $convertedQuotationCount ?? 0,
                'amount' => $this->formatService->cleanDecimal($convertedQuotationAmount ?? 0),
            ];
        })->toArray();
    }

    /**
     * Get sales dashboard data for a specific sales user
     */
    public function getSalesDashboardData(int $userId, ?FinancialYear $selectedYear = null): array
    {
        $now = Carbon::now();
        $currentFiscalYear = $selectedYear ?? FinancialYear::getCurrentYear();
        $fiscalYearStart = $currentFiscalYear ? Carbon::parse($currentFiscalYear->start_date) : Carbon::now()->startOfYear();
        $fiscalYearEnd = $currentFiscalYear ? Carbon::parse($currentFiscalYear->end_date) : Carbon::now()->endOfYear();

        $totalOrders = Order::whereHas('quotation', function ($q) use ($userId) {
            $q->where('status', '!=', 'cancelled')->whereHas('customer', function ($query) use ($userId) {
                $query->where('handled_by', $userId);
            });
        })->whereBetween('created_at', [$fiscalYearStart, $fiscalYearEnd])->count();

        $fiscalDayOfMonth = $fiscalYearStart->day;
        if ($now->day >= $fiscalDayOfMonth) {
            $currentFiscalMonthStart = Carbon::create($now->year, $now->month, $fiscalDayOfMonth);
        } else {
            $currentFiscalMonthStart = Carbon::create($now->year, $now->month, $fiscalDayOfMonth)->subMonth();
        }
        $currentFiscalMonthEnd = $currentFiscalMonthStart->copy()->addMonth()->subDay();
        $previousFiscalMonthStart = $currentFiscalMonthStart->copy()->subMonth();
        $previousFiscalMonthEnd = $currentFiscalMonthStart->copy()->subDay();

        $convertedQuotations = Quotation::where('status', 'converted')->with('quotationItems')->whereHas('customer', function ($query) use ($userId) {
            $query->where('handled_by', $userId);
        })->whereBetween('quotation_date', [$fiscalYearStart, $fiscalYearEnd])->get();

        $totalRevenue = $convertedQuotations->sum(function ($quotation) {
            return $quotation->total_amount;
        });

        $previousFiscalYear = FinancialYear::where('is_active', true)->where('start_date', '<', $fiscalYearStart)->orderBy('start_date', 'desc')->first();

        $previousRevenue = 0;
        if ($previousFiscalYear) {
            $previousYearStart = Carbon::parse($previousFiscalYear->start_date);
            $previousYearEnd = Carbon::parse($previousFiscalYear->end_date);

            $previousConvertedQuotations = Quotation::where('status', 'converted')->with('quotationItems')->whereHas('customer', function ($query) use ($userId) {
                $query->where('handled_by', $userId);
            })->whereBetween('quotation_date', [$previousYearStart, $previousYearEnd])->get();

            $previousRevenue = $previousConvertedQuotations->sum(function ($quotation) {
                return $quotation->total_amount;
            });
        }

        // Get current month revenue from converted quotations
        $currentMonthConvertedQuotations = Quotation::where('status', 'converted')->whereHas('customer', function ($query) use ($userId) {
            $query->where('handled_by', $userId);
        })->whereBetween('quotation_date', [$currentFiscalMonthStart, $currentFiscalMonthEnd])->get();

        $currentMonthRevenue = $currentMonthConvertedQuotations->sum(function ($quotation) {
            return $quotation->total_amount;
        });

        $previousMonthCustomers = Customer::where('handled_by', $userId)->whereBetween('created_at', [$previousFiscalMonthStart, $previousFiscalMonthEnd])->count();
        $currentMonthCustomers = Customer::where('handled_by', $userId)->whereBetween('created_at', [$currentFiscalMonthStart, $currentFiscalMonthEnd])->count();

        $previousMonthOrders = Order::whereHas('quotation.customer', function ($query) use ($userId) {
            $query->where('handled_by', $userId);
        })->whereHas('quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('created_at', [$previousFiscalMonthStart, $previousFiscalMonthEnd])->count();

        $currentMonthOrders = Order::whereHas('quotation.customer', function ($query) use ($userId) {
            $query->where('handled_by', $userId);
        })->whereHas('quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('created_at', [$currentFiscalMonthStart, $currentFiscalMonthEnd])->count();

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'previous_revenue' => $previousRevenue,
            'current_month_revenue' => $currentMonthRevenue,
            'current_month_customers' => $currentMonthCustomers,
            'previous_month_customers' => $previousMonthCustomers,
            'current_month_orders' => $currentMonthOrders,
            'previous_month_orders' => $previousMonthOrders,
            'current_fiscal_month_start' => $currentFiscalMonthStart,
            'current_fiscal_month_end' => $currentFiscalMonthEnd,
            'fiscal_year_start' => $fiscalYearStart,
            'fiscal_year_end' => $fiscalYearEnd,
            'now' => $now,
        ];
    }

    /**
     * Get fiscal year total sales (FYTD # and $) - converted quotations count and amount
     */
    public function getFiscalYearTotalSales(?FinancialYear $fiscalYear = null): array
    {
        $currentFiscalYear = $fiscalYear ?? FinancialYear::getCurrentYear();

        if (! $currentFiscalYear) {
            return [
                'count' => 0,
                'amount' => 0,
            ];
        }

        $fiscalYearStart = Carbon::parse($currentFiscalYear->start_date);
        $fiscalYearEnd = Carbon::parse($currentFiscalYear->end_date);

        $convertedQuotations = Quotation::where('status', 'converted')
            ->whereBetween('quotation_date', [$fiscalYearStart, $fiscalYearEnd])
            ->get();

        $count = $convertedQuotations->count();
        $amount = $convertedQuotations->sum(function ($quotation) {
            return $quotation->total_amount;
        });

        return [
            'count' => $count,
            'amount' => $this->formatService->cleanDecimal($amount),
        ];
    }

    /**
     * Get revenue by month (quotation count and amount per month in fiscal year)
     */
    public function getRevenueByMonth(?FinancialYear $fiscalYear = null): array
    {
        $currentFiscalYear = $fiscalYear ?? FinancialYear::getCurrentYear();

        if (! $currentFiscalYear) {
            return [];
        }

        $fiscalYearStart = Carbon::parse($currentFiscalYear->start_date);
        $fiscalYearEnd = Carbon::parse($currentFiscalYear->end_date);

        $monthlyData = [];
        $currentPeriodStart = $fiscalYearStart->copy();

        for ($i = 0; $i < 12; $i++) {
            $periodEnd = $currentPeriodStart->copy()->addMonth()->subDay();

            if ($periodEnd->greaterThan($fiscalYearEnd)) {
                $periodEnd = $fiscalYearEnd->copy();
            }

            if ($fiscalYearStart->day === 1) {
                $label = $currentPeriodStart->translatedFormat('F');
            } else {
                $label = $currentPeriodStart->translatedFormat('d F');
            }

            $quotations = Quotation::where('status', 'converted')
                ->whereBetween('quotation_date', [$currentPeriodStart, $periodEnd])
                ->get();

            $count = $quotations->count();
            $amount = $quotations->sum(function ($quotation) {
                return $quotation->total_amount;
            });

            $monthlyData[] = [
                'label' => $label,
                'count' => $count,
                'amount' => $this->formatService->cleanDecimal($amount),
                'start_date' => $currentPeriodStart->format('Y-m-d'),
                'end_date' => $periodEnd->format('Y-m-d'),
            ];

            $currentPeriodStart->addMonth();

            if ($currentPeriodStart->greaterThan($fiscalYearEnd)) {
                break;
            }
        }

        return $monthlyData;
    }

    /**
     * Get income by month (total invoice amount by month)
     */
    public function getIncomeByMonth(?FinancialYear $fiscalYear = null, ?string $status = null): array
    {
        $currentFiscalYear = $fiscalYear ?? FinancialYear::getCurrentYear();

        if (! $currentFiscalYear) {
            return [];
        }

        $fiscalYearStart = Carbon::parse($currentFiscalYear->start_date);
        $fiscalYearEnd = Carbon::parse($currentFiscalYear->end_date);

        $monthlyData = [];
        $currentPeriodStart = $fiscalYearStart->copy();

        for ($i = 0; $i < 12; $i++) {
            $periodEnd = $currentPeriodStart->copy()->addMonth()->subDay();

            if ($periodEnd->greaterThan($fiscalYearEnd)) {
                $periodEnd = $fiscalYearEnd->copy();
            }

            $query = Invoice::whereBetween('due_date', [$currentPeriodStart, $periodEnd])
                ->whereHas('order.quotation', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                });

            if ($status) {
                $query->where('status', $status);
            } else {
                $query->where('status', '!=', 'cancelled');
            }

            $totalAmount = $query->sum('amount');

            $monthlyData[] = [
                'date' => $currentPeriodStart->format('Y-m-d'),
                'amount' => $this->formatService->cleanDecimal($totalAmount),
            ];

            $currentPeriodStart->addMonth();

            if ($currentPeriodStart->greaterThan($fiscalYearEnd)) {
                break;
            }
        }

        return $monthlyData;
    }
}
