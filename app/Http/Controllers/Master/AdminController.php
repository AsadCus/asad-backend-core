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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    protected SalesUserService $adminRoleService;

    protected UserService $userService;

    protected BranchService $branchService;

    protected CountryService $countryService;

    protected SalesService $salesService;

    protected UserRule $userRule;

    public function __construct(UserService $userService, BranchService $branchService, CountryService $countryService, SalesService $salesService, UserRule $userRule)
    {
        $this->adminRoleService = new SalesUserService('admin');
        $this->userService = $userService;
        $this->branchService = $branchService;
        $this->countryService = $countryService;
        $this->salesService = $salesService;
        $this->userRule = $userRule;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $dataUser = $this->adminRoleService->getForDataTable();
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/admin/index', [
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
    public function create(): Response
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
            'isAdmin' => true,
            'submitUrl' => '/master/user/admin',
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->userRule->rules('admin'));

        $user = $this->adminRoleService->store($validated);

        if (! empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        activity()
            ->performedOn($user)
            ->withProperties(['subject_type' => 'Admin', 'subject_id' => $user->id ?? null])
            ->log('Admin created successfully #'.($user->id ?? null));

        return redirect()->intended(route('master.user.admin.index'))
            ->with('success', 'Admin created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): Response
    {
        $data = $this->adminRoleService->getForEditShow($id);
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
            'isAdmin' => true,
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): Response
    {
        $data = $this->adminRoleService->getForEditShow($id);
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
            'isAdmin' => true,
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate($this->userRule->rules('admin', 'update', $id));

        $this->adminRoleService->update($validated, $id);

        return redirect()->intended(route('master.user.admin.index'))
            ->with('success', 'Admin updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->userService->delete($userId);
            }

            return redirect()->intended(route('master.user.admin.index'))
                ->with('success', 'Selected admins deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('master.user.admin.index'))
            ->with('success', 'Admin deleted successfully.');
    }
}
