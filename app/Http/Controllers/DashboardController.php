<?php

namespace App\Http\Controllers;

use App\Helpers\FormatService;
use App\Services\CountryService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\CustomerService;
use App\Services\EducationLevelService;
use App\Services\MaidService;
use App\Services\OrderService;
use App\Services\ReligionService;
use App\Services\SupplierService;
use App\Services\SalesService;
use App\Services\FinancialYearService;
use App\Models\FinancialYear;
use App\Services\FinancialTransactionService;

class DashboardController extends Controller
{
    protected $customerService, $maidService, $countryService, $religionService, $educationLevelService, $supplierService, $salesService, $financialYearService, $financialTransactionService, $formatService, $orderService;

    public function __construct(
        CustomerService $customerService,
        MaidService $maidService,
        CountryService $countryService,
        ReligionService $religionService,
        EducationLevelService $educationLevelService,
        SupplierService $supplierService,
        SalesService $salesService,
        FinancialYearService $financialYearService,
        FinancialTransactionService $financialTransactionService,
        FormatService $formatService,
        OrderService $orderService,
    ) {
        $this->customerService = $customerService;
        $this->maidService = $maidService;
        $this->countryService = $countryService;
        $this->religionService = $religionService;
        $this->educationLevelService = $educationLevelService;
        $this->supplierService = $supplierService;
        $this->salesService = $salesService;
        $this->financialYearService = $financialYearService;
        $this->financialTransactionService = $financialTransactionService;
        $this->formatService = $formatService;
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $data = [];

        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();

        if ($user->hasRole('admin')) {
            $availableYears = $this->financialYearService->getAvailableYears();

            if ($selectedYear) {
                $data['fiscalYear'] = $selectedYear->year;
                $data['selectedYearId'] = $selectedYear->id;
                $data['fiscalYearStartDate'] = $selectedYear->start_date->format('Y-m-d');

                $data['chartData'] = [
                    'financial' => $this->financialTransactionService->getChartData($selectedYear->id),
                ];

                $data['availableYears'] = $availableYears;
            }

            $data['customers'] = $this->customerService->getForDataTable($request);
        }

        if ($user->hasRole('sales')) {
            $data['customers'] = $this->customerService->getSalesCustomersData($user->id);

            $assignedCount = collect($data['customers'])->where('status', 'Assigned')->count();
            $unassignedCount = collect($data['customers'])->where('status', 'Unassigned')->count();

            $salesData = $this->salesService->getSalesDashboardData($user->id, $selectedYear);

            $data['widgets'] = [
                [
                    'title' => 'My Customers',
                    'value' => $assignedCount,
                    'previous' => $salesData['previous_month_customers'],
                    'current' => $salesData['current_month_customers'],
                    'period_start' => $salesData['current_fiscal_month_start']->format('d M Y'),
                    'period_end' => $salesData['current_fiscal_month_end']->format('d M Y'),
                    'period_type' => 'month'
                ],
                [
                    'title' => 'Unassigned Customers',
                    'value' => $unassignedCount,
                    'previous' => 0,
                    'current' => 0,
                    'period_start' => $salesData['current_fiscal_month_start']->format('d M Y'),
                    'period_end' => $salesData['current_fiscal_month_end']->format('d M Y'),
                    'period_type' => 'month'
                ],
                [
                    'title' => 'Total Orders (FY)',
                    'value' => $salesData['total_orders'],
                    'previous' => $salesData['previous_month_orders'],
                    'current' => $salesData['current_month_orders'],
                    'period_start' => $salesData['current_fiscal_month_start']->format('d M Y'),
                    'period_end' => $salesData['current_fiscal_month_end']->format('d M Y'),
                    'period_type' => 'month'
                ],
                [
                    'title' => 'Total Revenue (FY)',
                    'value' => $this->formatService->formatCurrency($salesData['total_revenue']),
                    'previous' => $salesData['previous_revenue'],
                    'current' => $salesData['total_revenue'],
                    'period_start' => $salesData['fiscal_year_start']->format('d M Y'),
                    'period_end' => $salesData['now']->format('d M Y'),
                    'period_type' => 'year'
                ],
            ];
        }

        if ($user->hasRole('customer')) {
            $customerMaidIds = $this->customerService->getCustomerMaidIds($user->id);

            if (!empty($customerMaidIds)) {
                $data['maids'] = $this->maidService->getForDataTable($customerMaidIds);
            } else {
                $data['maids'] = [];
            }

            $data['nationality'] = $this->countryService->getForFilterByAdjective();
            $data['religion'] = $this->religionService->getForFilterByName();
            $data['educationLevel'] = $this->educationLevelService->getForFilterByName();
            $data['supplier'] = $this->supplierService->getForFilterByName();
            $data['misc'] = [
                'nationalities' => $this->countryService->getForFilter(),
                'religions' => $this->religionService->getForFilter(),
                'education_levels' => $this->educationLevelService->getForFilter(),
                'suppliers' => $this->supplierService->getForFilter(),
            ];
        }

        return Inertia::render('dashboard', [
            'data' => $data,
        ]);
    }

    /**
     * Get sales period options for a specific financial year
     */
    public function getSalesPeriodOptions(Request $request)
    {
        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();

        if (!$selectedYear) {
            return response()->json([
                'options' => [],
                'default' => 'full-year'
            ]);
        }

        $periodData = $this->salesService->getSalesPeriodOptions($selectedYear);

        return response()->json($periodData);
    }

    /**
     * Get quotation converted by salesperson data
     */
    public function getQuotationConvertedBySalesperson(Request $request)
    {
        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();
        $period = $request->input('period', 'full-year');

        if (!$selectedYear) {
            return response()->json([]);
        }

        $data = $this->salesService->getQuotationConvertedBySalesperson($period, $selectedYear);

        return response()->json($data);
    }

    /**
     * Get sales dashboard data for a specific sales user
     */
    public function getSalesDashboardData(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('sales')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();

        $salesData = $this->salesService->getSalesDashboardData($user->id, $selectedYear);

        return response()->json($salesData);
    }

    /**
     * Get fiscal year total sales (FYTD # and $)
     */
    public function getFiscalYearTotalSales(Request $request)
    {
        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();

        if (!$selectedYear) {
            return response()->json(['count' => 0, 'amount' => 0]);
        }

        $data = $this->salesService->getFiscalYearTotalSales($selectedYear);

        return response()->json($data);
    }

    /**
     * Get revenue by month (quotation count and amount per month)
     */
    public function getRevenueByMonth(Request $request)
    {
        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();

        if (!$selectedYear) {
            return response()->json([]);
        }

        $data = $this->salesService->getRevenueByMonth($selectedYear);

        return response()->json($data);
    }

    /**
     * Get income by month (total invoice amount per month)
     */
    public function getIncomeByMonth(Request $request)
    {
        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();
        $status = $request->input('status', null);

        if (!$selectedYear) {
            return response()->json([]);
        }

        $data = $this->salesService->getIncomeByMonth($selectedYear, $status);

        return response()->json($data);
    }
}
