<?php

namespace App\Http\Controllers;

use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\SalesService;
use App\Services\UserRoles\SalesUserService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class SalesController extends Controller
{
    protected $salesService;

    protected $salesUserService;

    protected $branchService;

    protected $userService;

    protected $countryService;

    protected $userRule;

    public function __construct(SalesService $salesService, SalesUserService $salesUserService, BranchService $branchService, UserService $userService, CountryService $countryService, UserRule $userRule)
    {
        $this->salesService = $salesService;
        $this->salesUserService = $salesUserService;
        $this->branchService = $branchService;
        $this->userService = $userService;
        $this->countryService = $countryService;
        $this->userRule = $userRule;

        $this->middleware('permission:sales view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = $this->salesService->getForDataTable();
        $dataBranch = $this->branchService->getForFilter();

        return Inertia::render('sales/index', [
            'data' => $data,
            'dataBranch' => $dataBranch,
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
            'isSales' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules($request->role));

        $validated['role'] = 'sales';

        $this->salesUserService->store($validated);

        return redirect()->intended(route('sales.index'))->with('success', 'Sales created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->salesUserService->getForEditShow($id);
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
            'isSales' => true,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->salesUserService->getForEditShow($id);
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
            'isSales' => true,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules($request->role, 'update', $id));

        $validated['role'] = 'sales';

        $this->salesUserService->update($validated, $id);

        return redirect()->intended(route('sales.index'))->with('success', 'Sales updated successfully.');
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

            return redirect()->intended(route('sales.index'))->with('success', 'Selected sales deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('sales.index'))->with('success', 'Sales deleted successfully.');
    }
}
