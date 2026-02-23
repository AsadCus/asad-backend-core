<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\SalesService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    protected $userService;

    protected $branchService;

    protected $countryService;

    protected $salesService;

    protected $userRule;

    public function __construct(UserService $userService, BranchService $branchService, CountryService $countryService, SalesService $salesService, UserRule $userRule)
    {
        $this->userService = $userService;
        $this->branchService = $branchService;
        $this->countryService = $countryService;
        $this->salesService = $salesService;
        $this->userRule = $userRule;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $dataUser = $this->userService->getForDataTable();
        // $dataRole = $this->userService->getRoleForFilter();
        // $dataBranch = $this->branchService->getForFilter();
        // $dataCountry = $this->countryService->getForFilter();
        // $dataSales = $this->salesService->getForFilter();

        // Get role statistics
        $roleStats = [
            'admin' => $this->userService->countByRole('admin'),
            'sales' => $this->userService->countByRole('sales'),
            'customer' => $this->userService->countByRole('customer'),
            'supplier' => $this->userService->countByRole('supplier'),
        ];

        return Inertia::render('masters/users/index', [
            // 'data' => $dataUser,
            // 'dataRole' => $dataRole,
            // 'dataBranch' => $dataBranch,
            // 'dataCountry' => $dataCountry,
            // 'dataSales' => $dataSales,
            'roleStats' => $roleStats,
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
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules($request->role));

        $validated['role'] = $request->role;

        $this->userService->store($validated);

        return redirect()->intended(route('master.user.index'))->with('success', 'User created successfully.');
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
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules($request->role, 'update', $id));

        $validated['role'] = $request->role;

        $this->userService->update($validated, $id);

        return redirect()->intended(route('master.user.index'))->with('success', 'User updated successfully.');
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

            return redirect()->intended(route('master.user.index'))->with('success', 'Selected users deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('master.user.index'))->with('success', 'User deleted successfully.');
    }
}
