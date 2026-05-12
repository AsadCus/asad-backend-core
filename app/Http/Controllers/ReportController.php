<?php

namespace App\Http\Controllers;

use App\Services\FinancialTransactionService;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(
        protected FinancialTransactionService $financialTransactionService,
        protected PackageService $packageService,
    ) {}

    public function paymentIndex(Request $request)
    {
        return Inertia::render('sales/reports/payment/index', [
            'packageOptions' => $this->packageService->getForFilter(),
            'categoryOptions' => $this->financialTransactionService->getAvailableCategories(),
        ]);
    }

    public function closingIndex(Request $request)
    {
        return Inertia::render('sales/reports/closing/index', [
            'packageOptions' => $this->packageService->getForFilter(),
            'categoryOptions' => $this->financialTransactionService->getAvailableCategories(),
        ]);
    }

    public function closingData(Request $request)
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

        $data = $this->financialTransactionService->getPackageGroupPaymentSummary(
            $period,
            $financialYearId ? (int) $financialYearId : null,
            is_string($timezone) ? $timezone : null,
            is_string($rangeStartUtc) ? $rangeStartUtc : null,
            is_string($rangeEndUtc) ? $rangeEndUtc : null,
            empty($packageIds) ? null : (is_array($packageIds) ? $packageIds : null),
            empty($categoryIds) ? null : (is_array($categoryIds) ? $categoryIds : null),
        );

        return response()->json($data);
    }
}
