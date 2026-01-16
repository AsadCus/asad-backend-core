<?php

namespace App\Http\Controllers;

use App\Rules\UserRule;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Services\UserService;
use App\Services\SalesService;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\EducationLevelService;
use App\Services\MaidService;
use App\Services\ReligionService;
use App\Services\SupplierService;

class SupplierController extends Controller
{
    protected $supplierService, $userService, $branchService, $countryService, $salesService, $userRule, $maidService, $religionService, $educationLevelService;

    public function __construct(SupplierService $supplierService, UserService $userService, BranchService $branchService, CountryService $countryService, SalesService $salesService, UserRule $userRule, MaidService $maidService, ReligionService $religionService, EducationLevelService $educationLevelService)
    {
        $this->supplierService = $supplierService;
        $this->userService = $userService;
        $this->branchService = $branchService;
        $this->countryService = $countryService;
        $this->salesService = $salesService;
        $this->userRule = $userRule;
        $this->maidService = $maidService;
        $this->religionService = $religionService;
        $this->educationLevelService = $educationLevelService;

        $this->middleware('permission:supplier view', ['only' => ['index', 'show']]);
        $this->middleware('permission:supplier create', ['only' => ['create', 'store']]);
        $this->middleware('permission:supplier edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:supplier delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $maids = $this->maidService->getForDataTable();

        $data['suppliers'] = $this->supplierService->getForDataTable();
        $data['maidsBySupplier'] = $maids->groupBy('supplier_id');
        $data['misc'] = [
            'nationalities' => $this->countryService->getForFilter(),
            'religions' => $this->religionService->getForFilter(),
            'education_levels' => $this->educationLevelService->getForFilter(),
            'suppliers' => $this->supplierService->getForFilter(),
        ];

        return Inertia::render('supplier/index', [
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilterByName();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/create', [
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSupplier' => true
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules($request->role));

        $this->userService->store($validated);

        return redirect()->intended(route('supplier.index'))->with('success', 'Supplier created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->userService->getForEditShow($id);
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilterByName();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/view', [
            'data' => $data,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSupplier' => true
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->userService->getForEditShow($id);
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilterByName();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/edit', [
            'data' => $data,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSupplier' => true
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules($request->role, 'update', $id));

        $this->userService->update($validated, $id);

        return redirect()->intended(route('supplier.index'))->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->userService->delete($userId);
            }

            return redirect()->intended(route('supplier.index'))->with('success', 'Selected suppliers deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('supplier.index'))->with('success', 'Supplier deleted successfully.');
    }
}
