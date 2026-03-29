<?php

namespace App\Http\Controllers;

use App\Helpers\FormatService;
use App\Models\FinancialYear;
use App\Services\CountryService;
use App\Services\CustomerService;
use App\Services\EducationLevelService;
use App\Services\EnquiryService;
use App\Services\FinancialTransactionService;
use App\Services\FinancialYearService;
use App\Services\OrderService;
use App\Services\ReligionService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $customerService;

    protected $countryService;

    protected $religionService;

    protected $educationLevelService;

    protected $salesService;

    protected $financialYearService;

    protected $financialTransactionService;

    protected $formatService;

    protected $orderService;

    protected $enquiryService;

    protected $reportTemplateService;

    public function __construct(
        CustomerService $customerService,
        CountryService $countryService,
        ReligionService $religionService,
        EducationLevelService $educationLevelService,
        SalesService $salesService,
        FinancialYearService $financialYearService,
        FinancialTransactionService $financialTransactionService,
        FormatService $formatService,
        OrderService $orderService,
        EnquiryService $enquiryService,
        ReportTemplateService $reportTemplateService,
    ) {
        $this->customerService = $customerService;
        $this->countryService = $countryService;
        $this->religionService = $religionService;
        $this->educationLevelService = $educationLevelService;
        $this->salesService = $salesService;
        $this->financialYearService = $financialYearService;
        $this->financialTransactionService = $financialTransactionService;
        $this->formatService = $formatService;
        $this->orderService = $orderService;
        $this->enquiryService = $enquiryService;
        $this->reportTemplateService = $reportTemplateService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $data = [];

        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $selectedYearId ? FinancialYear::find($selectedYearId) : FinancialYear::getCurrentYear();

        if (! $selectedYear) {
            $selectedYear = FinancialYear::where('is_active', true)
                ->orderBy('start_date', 'desc')
                ->first();
        }

        if ($user->hasRole('admin')) {
            $availableYears = $this->financialYearService->getAvailableYears();
            $data['availableYears'] = $availableYears;

            if ($selectedYear) {
                $data['fiscalYear'] = $selectedYear->year;
                $data['selectedYearId'] = $selectedYear->id;
                $data['fiscalYearStartDate'] = $selectedYear->start_date->format('Y-m-d');

                $data['chartData'] = [
                    'financial' => $this->financialTransactionService->getChartData($selectedYear->id),
                ];
            }

            $data['customers'] = $this->customerService->getForDataTable($request);
        }

        if ($user->hasRole('sales')) {
            $data['customers'] = $this->customerService->getSalesCustomersData($user->id);

            $assignedCount = collect($data['customers'])->where('status', 'Assigned')->count();
            $unassignedCount = collect($data['customers'])->where('status', 'Unassigned')->count();

            $salesData = $this->salesService->getSalesDashboardData($user->id, $selectedYear);

            $data['enquiries'] = $this->enquiryService->getForDataTable();
            $data['enquirySummary'] = $this->enquiryService->getSummaryCounts();

            $data['widgets'] = [
                [
                    'title' => 'My Customers',
                    'value' => $assignedCount,
                    'previous' => $salesData['previous_month_customers'],
                    'current' => $salesData['current_month_customers'],
                    'period_start' => $salesData['current_fiscal_month_start']->format('d M Y'),
                    'period_end' => $salesData['current_fiscal_month_end']->format('d M Y'),
                    'period_type' => 'month',
                ],
                [
                    'title' => 'Unassigned Customers',
                    'value' => $unassignedCount,
                    'previous' => 0,
                    'current' => 0,
                    'period_start' => $salesData['current_fiscal_month_start']->format('d M Y'),
                    'period_end' => $salesData['current_fiscal_month_end']->format('d M Y'),
                    'period_type' => 'month',
                ],
                [
                    'title' => 'Total Orders (FY)',
                    'value' => $salesData['total_orders'],
                    'previous' => $salesData['previous_month_orders'],
                    'current' => $salesData['current_month_orders'],
                    'period_start' => $salesData['current_fiscal_month_start']->format('d M Y'),
                    'period_end' => $salesData['current_fiscal_month_end']->format('d M Y'),
                    'period_type' => 'month',
                ],
                [
                    'title' => 'Total Revenue (FY)',
                    'value' => $this->formatService->formatCurrency($salesData['total_revenue']),
                    'previous' => $salesData['previous_revenue'],
                    'current' => $salesData['total_revenue'],
                    'period_start' => $salesData['fiscal_year_start']->format('d M Y'),
                    'period_end' => $salesData['now']->format('d M Y'),
                    'period_type' => 'year',
                ],
            ];
        }

        if ($user->hasRole('customer')) {
            $data['nationality'] = $this->countryService->getForFilterByAdjective();
            $data['religion'] = $this->religionService->getForFilterByName();
            $data['educationLevel'] = $this->educationLevelService->getForFilterByName();
            $data['misc'] = [
                'nationalities' => $this->countryService->getForFilter(),
                'religions' => $this->religionService->getForFilter(),
                'education_levels' => $this->educationLevelService->getForFilter(),
            ];
        }

        return Inertia::render('dashboard', [
            'data' => $data,
        ]);
    }

    /**
     * Get sales dashboard data for a specific sales user
     */
    public function getSalesDashboardData(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('sales')) {
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

        if (! $selectedYear) {
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

        if (! $selectedYear) {
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

        if (! $selectedYear) {
            return response()->json([]);
        }

        $data = $this->salesService->getIncomeByMonth($selectedYear, $status);

        return response()->json($data);
    }

    public function getPaymentSummaryByPeriod(Request $request)
    {
        $period = (string) $request->input('period', 'daily');
        $selectedYearId = $request->input('financial_year_id');
        $timezone = $request->input('timezone');
        $rangeStartUtc = $request->input('range_start_utc');
        $rangeEndUtc = $request->input('range_end_utc');

        $data = $this->financialTransactionService->getPaymentCategorySummary(
            $period,
            $selectedYearId ? (int) $selectedYearId : null,
            is_string($timezone) ? $timezone : null,
            is_string($rangeStartUtc) ? $rangeStartUtc : null,
            is_string($rangeEndUtc) ? $rangeEndUtc : null,
        );

        return response()->json($data);
    }

    public function exportPaymentSummaryPdf(Request $request)
    {
        $period = (string) $request->input('period', 'daily');
        $selectedYearId = $request->input('financial_year_id');
        $timezone = $request->input('timezone');
        $rangeStartUtc = $request->input('range_start_utc');
        $rangeEndUtc = $request->input('range_end_utc');

        $summary = $this->financialTransactionService->getPaymentCategorySummary(
            $period,
            $selectedYearId ? (int) $selectedYearId : null,
            is_string($timezone) ? $timezone : null,
            is_string($rangeStartUtc) ? $rangeStartUtc : null,
            is_string($rangeEndUtc) ? $rangeEndUtc : null,
        );

        // Build report with branding using Report Template Service
        $report = $this->reportTemplateService->build('payment_summary', $summary);

        $pdf = Pdf::loadView('reports.dashboard-payment-summary', $report)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'payment-summary-'.$summary['period'].'-'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }
}
