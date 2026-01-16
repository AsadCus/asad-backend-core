<?php

namespace App\Http\Controllers;

use App\Services\CountryService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\CustomerService;
use App\Services\EducationLevelService;
use App\Services\MaidService;
use App\Services\ReligionService;
use App\Services\SupplierService;

class DashboardController extends Controller
{
    protected $customerService, $maidService, $countryService, $religionService, $educationLevelService, $supplierService;

    public function __construct(
        CustomerService $customerService,
        MaidService $maidService,
        CountryService $countryService,
        ReligionService $religionService,
        EducationLevelService $educationLevelService,
        SupplierService $supplierService,
    ) {
        $this->customerService = $customerService;
        $this->maidService = $maidService;
        $this->countryService = $countryService;
        $this->religionService = $religionService;
        $this->educationLevelService = $educationLevelService;
        $this->supplierService = $supplierService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $data = [];

        if ($user->hasRole(['sales', 'admin'])) {
            $data['widgets'] = [
                ['title' => 'Total Customers', 'value' => $this->customerService->getTotalCount()],
                ['title' => 'Active Maids', 'value' => $this->maidService->getActiveCount()],
                ['title' => 'Total Orders', 'value' => 0],
                ['title' => 'Total Revenue', 'value' => 0],
            ];

            $data['customers'] = $this->customerService->getForDataTable($request);

            $data['chartData'] = [
                'customers' => [
                    '90d' => $this->customerService->getMonthlyStats(),
                    '30d' => $this->customerService->getDailyStats(30),
                    '7d' => $this->customerService->getDailyStats(7),
                ],
                'maids' => [
                    '90d' => $this->maidService->getMonthlyStats(),
                    '30d' => $this->maidService->getDailyStats(30),
                    '7d' => $this->maidService->getDailyStats(7),
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
}
