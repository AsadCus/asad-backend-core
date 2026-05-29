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
use App\Services\PackageService;
use App\Services\ReligionService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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

    protected $packageService;

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
        PackageService $packageService
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
        $this->packageService = $packageService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (
            ($user->hasRole('sales') || $user->hasRole('admin'))
            && ! $user->hasRole('superadmin')
        ) {
            return redirect()->route('enquiries.index');
        }

        $data = [];

        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $this->resolveDashboardFinancialYear($selectedYearId);

        if ($user->hasRole('superadmin')) {
            $availableYears = $this->financialYearService->getAvailableYears();
            $data['availableYears'] = $availableYears;

            if ($selectedYear) {
                $data['fiscalYear'] = $selectedYear->year;
                $data['selectedYearId'] = $selectedYear->id;
                $data['fiscalYearStartDate'] = $selectedYear->start_date?->format('Y-m-d');

                $data['chartData'] = [
                    'financial' => $this->financialTransactionService->getChartData($selectedYear->id),
                ];
            }

            $data['enquiries'] = $this->enquiryService->getForDataTable();

            $data['packageOptions'] = $this->packageService->getForFilter();
            $data['categoryOptions'] = $this->financialTransactionService->getAvailableCategories();
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

    public function fiscalYearSales(Request $request)
    {
        $selectedYearId = $request->input('financial_year_id');
        $selectedYear = $this->resolveDashboardFinancialYear($selectedYearId);

        if (! $selectedYear) {
            return response()->json(['count' => 0, 'amount' => 0, 'by_country' => []]);
        }

        $data = $this->salesService->getFiscalYearTotalSales($selectedYear);

        return response()->json($data);
    }

    public function paymentReport(Request $request)
    {
        $period = (string) $request->input('period', 'daily');
        $selectedYearId = $request->input('financial_year_id');
        $timezone = $request->input('timezone');
        $rangeStartUtc = $request->input('range_start_utc');
        $rangeEndUtc = $request->input('range_end_utc');

        $packageIds = $request->input('packages');
        if (is_string($packageIds)) {
            $packageIds = array_map('intval', array_filter(explode(',', $packageIds)));
        }

        $categoryIds = $request->input('categories');
        if (is_string($categoryIds)) {
            $categoryIds = array_filter(explode(',', $categoryIds));
        }

        $data = $this->financialTransactionService->getPaymentCategorySummary(
            $period,
            $selectedYearId ? (int) $selectedYearId : null,
            is_string($timezone) ? $timezone : null,
            is_string($rangeStartUtc) ? $rangeStartUtc : null,
            is_string($rangeEndUtc) ? $rangeEndUtc : null,
            empty($packageIds) ? null : (is_array($packageIds) ? $packageIds : null),
            empty($categoryIds) ? null : (is_array($categoryIds) ? $categoryIds : null),
        );

        return response()->json($data);
    }

    public function exportPaymentReport(Request $request)
    {
        $period = (string) $request->input('period', 'daily');
        $selectedYearId = $request->input('financial_year_id');
        $timezone = $request->input('timezone');
        $rangeStartUtc = $request->input('range_start_utc');
        $rangeEndUtc = $request->input('range_end_utc');

        $packageIds = $request->input('packages');
        if (is_string($packageIds)) {
            $packageIds = array_map('intval', array_filter(explode(',', $packageIds)));
        }

        $categoryIds = $request->input('categories');
        if (is_string($categoryIds)) {
            $categoryIds = array_filter(explode(',', $categoryIds));
        }

        $summary = $this->financialTransactionService->getPaymentCategorySummary(
            $period,
            $selectedYearId ? (int) $selectedYearId : null,
            is_string($timezone) ? $timezone : null,
            is_string($rangeStartUtc) ? $rangeStartUtc : null,
            is_string($rangeEndUtc) ? $rangeEndUtc : null,
            empty($packageIds) ? null : (is_array($packageIds) ? $packageIds : null),
            empty($categoryIds) ? null : (is_array($categoryIds) ? $categoryIds : null),
        );

        // Build report with branding using Report Template Service
        $report = $this->reportTemplateService->build('payment_summary', $summary);
        $report['is_pdf'] = true;

        $pdf = Pdf::loadView('dashboard.payment-report', $report)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'payment-summary-'.$summary['period'].'-'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    public function exportClosingReport(Request $request): Response
    {
        $period = (string) $request->input('period', 'monthly');
        $financialYearId = $request->input('financial_year_id');
        $timezone = $request->input('timezone');
        $rangeStartUtc = $request->input('range_start_utc');
        $rangeEndUtc = $request->input('range_end_utc');
        $packageIds = $request->input('package_id');
        if (is_string($packageIds)) {
            $packageIds = explode(',', $packageIds);
        }
        if (is_array($packageIds)) {
            $packageIds = array_map('intval', array_filter($packageIds));
        }

        $categoryIds = $request->input('categories');
        if (is_string($categoryIds)) {
            $categoryIds = array_filter(explode(',', $categoryIds));
        }

        $summary = $this->financialTransactionService->getPackageGroupPaymentSummary(
            $period,
            $financialYearId ? (int) $financialYearId : null,
            is_string($timezone) ? $timezone : null,
            is_string($rangeStartUtc) ? $rangeStartUtc : null,
            is_string($rangeEndUtc) ? $rangeEndUtc : null,
            empty($packageIds) ? null : (is_array($packageIds) ? $packageIds : null),
            empty($categoryIds) ? null : (is_array($categoryIds) ? $categoryIds : null),
        );

        if (! empty($packageIds) && count($packageIds) > 1 && isset($summary['package'])) {
            $pkgList = \App\Models\Package::whereIn('id', $packageIds)
                ->get(['package_number', 'name'])
                ->map(fn ($p) => $p->package_number.' - '.$p->name)
                ->implode(', ');
            $summary['package']['name'] = $pkgList ?: (count($packageIds).' packages');
        }

        $summary['selected_categories'] = ! empty($categoryIds)
            ? array_values((array) $categoryIds)
            : null;

        $report = $this->reportTemplateService->build('closing_report', $summary);
        $report['is_pdf'] = true;

        $pdf = Pdf::loadView('dashboard.closing-report', $report)
            ->setPaper('a4', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $packageLabel = isset($summary['package']['package_number'])
            ? '-'.strtolower($summary['package']['package_number'])
            : '';

        $filename = 'closing-report'.$packageLabel.'-'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    private function resolveDashboardFinancialYear($selectedYearId): ?FinancialYear
    {
        if ($selectedYearId) {
            $requestedYear = FinancialYear::query()
                ->whereKey($selectedYearId)
                ->whereNotNull('start_date')
                ->whereNotNull('end_date')
                ->first();

            if ($requestedYear) {
                return $requestedYear;
            }
        }

        return FinancialYear::query()
            ->where('is_active', true)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->orderByDesc('default')
            ->orderByDesc('start_date')
            ->first();
    }
}
