<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\SalesService;
use App\Services\UserRoles\AdminUserService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminController extends Controller
{
    protected $adminUserService;

    protected $userService;

    protected $branchService;

    protected $countryService;

    protected $salesService;

    protected $userRule;

    public function __construct(AdminUserService $adminUserService, UserService $userService, BranchService $branchService, CountryService $countryService, SalesService $salesService, UserRule $userRule)
    {
        $this->adminUserService = $adminUserService;
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
        $dataUser = $this->adminUserService->getForDataTable();
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/admin/index', [
            'dataUser' => $dataUser,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataSales' => $dataSales,
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
            'isAdmin' => true,
            'submitUrl' => '/master/user/admin',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules('admin'));

        $user = $this->adminUserService->store($validated);

        if (! empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'Admin', 'subject_id' => $user->id ?? null])
                ->log('Admin created successfully #'.($user->id ?? null));

        return redirect()->intended(route('master.user.admin.index'))->with('success', 'Administrator created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->adminUserService->getForEditShow($id);
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
            'isAdmin' => true,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->adminUserService->getForEditShow($id);
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
            'isAdmin' => true,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules('admin', 'update', $id));

        $this->adminUserService->update($validated, $id);

        return redirect()->intended(route('master.user.admin.index'))->with('success', 'Administrator updated successfully.');
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

            return redirect()->intended(route('master.user.admin.index'))->with('success', 'Selected administrators deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('master.user.admin.index'))->with('success', 'Administrator deleted successfully.');
    }
}
