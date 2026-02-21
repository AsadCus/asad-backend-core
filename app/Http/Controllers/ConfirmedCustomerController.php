<?php

namespace App\Http\Controllers;

use App\Services\CustomerGroupService;
use App\Services\PackageService;
use Inertia\Inertia;

class ConfirmedCustomerController extends Controller
{
    public function __construct(
        protected CustomerGroupService $customerGroupService,
        protected PackageService $packageService,
    ) {}

    /**
     * Display a listing of confirmed customer groups.
     */
    public function index()
    {
        $dataGroups = $this->customerGroupService->getForGroupedIndex();
        $packageOptions = $this->packageService->getForFilter();

        return Inertia::render('confirmed-customer/index', [
            'dataGroups' => $dataGroups,
            'packageOptions' => $packageOptions,
        ]);
    }
}
