<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\SalesService;
use App\Services\UserRoles\SalesUserService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class SalesController extends Controller
{
    protected $salesUserService;

    protected $userService;

    protected $branchService;

    protected $countryService;

    protected $salesService;

    protected $userRule;

    public function __construct(SalesUserService $salesUserService, UserService $userService, BranchService $branchService, CountryService $countryService, SalesService $salesService, UserRule $userRule)
    {
        $this->salesUserService = $salesUserService;
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
        $dataUser = $this->salesUserService->getForDataTable();
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/sales/index', [
            'dataUser' => $dataUser,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/create', [
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSales' => true,
            'submitUrl' => '/master/user/sales',
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules('sales'));

        $user = $this->salesUserService->store($validated);

        if (! empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        activity()
            ->performedOn($user)
            ->withProperties(['subject_type' => 'Sales', 'subject_id' => $user->id ?? null])
            ->log('Sales created successfully #'.($user->id ?? null));

        return redirect()->intended(route('master.user.sales.index'))->with('success', 'Sales created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->salesUserService->getForEditShow($id);
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/view', [
            'data' => $data,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSales' => true,
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
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
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/edit', [
            'data' => $data,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSales' => true,
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules('sales', 'update', $id));

        $this->salesUserService->update($validated, $id);

        return redirect()->intended(route('master.user.sales.index'))->with('success', 'Sales updated successfully.');
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

            return redirect()->intended(route('master.user.sales.index'))->with('success', 'Selected sales deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('master.user.sales.index'))->with('success', 'Sales deleted successfully.');
    }
}
