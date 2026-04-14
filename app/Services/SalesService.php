<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use App\Support\InvoiceStatus;
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
     * Get fiscal year total sales (FYTD # and $) based on paid converted invoices.
     *
     * FYTD window is derived from receipt payment date (receipt_date),
     * not invoice schedule date, so installment invoices paid today are included.
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
        $today = Carbon::now();
        $fiscalYearToDateEnd = $today;

        if ($fiscalYearToDateEnd->lessThan($fiscalYearStart)) {
            return [
                'count' => 0,
                'amount' => 0,
            ];
        }

        $paidAndRefundConvertedInvoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Paid, InvoiceStatus::Refund])
            ->whereHas('order.quotation', function ($query) {
                $query->where('status', 'converted');
            })
            ->whereHas('receipt', function ($query) use ($fiscalYearStart, $fiscalYearToDateEnd) {
                $query->whereBetween('receipt_date', [$fiscalYearStart, $fiscalYearToDateEnd]);
            });

        $count = (clone $paidAndRefundConvertedInvoices)
            ->where('status', InvoiceStatus::Paid)
            ->count();
        $amount = (clone $paidAndRefundConvertedInvoices)->sum('amount');

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
