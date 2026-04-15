<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\CustomerConfirmationService;
use App\Services\CustomerService;
use App\Services\PackageService;
use App\Services\SalesService;
use App\Services\UserRoles\CustomerUserService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CustomerController extends Controller
{
    protected $customerUserService;

    protected $customerService;

    protected $branchService;

    protected $salesService;

    protected $countryService;

    protected $userService;

    protected $userRule;

    protected $customerConfirmationService;

    protected $packageService;

    public function __construct(
        CustomerService $customerService,
        CustomerUserService $customerUserService,
        BranchService $branchService,
        SalesService $salesService,
        CountryService $countryService,
        UserService $userService,
        UserRule $userRule,
        CustomerConfirmationService $customerConfirmationService,
        PackageService $packageService,
    ) {
        $this->customerService = $customerService;
        $this->customerUserService = $customerUserService;
        $this->branchService = $branchService;
        $this->salesService = $salesService;
        $this->countryService = $countryService;
        $this->userService = $userService;
        $this->userRule = $userRule;
        $this->customerConfirmationService = $customerConfirmationService;
        $this->packageService = $packageService;

        $this->middleware('permission:customer view', ['only' => ['index', 'show']]);
        $this->middleware('permission:customer create', ['only' => ['create', 'store']]);
        $this->middleware('permission:customer edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:customer delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $data = $this->customerService->getForDataTable($request);
        $dataBranch = $this->branchService->getForFilter();
        $dataSales = $this->salesService->getForFilter();
        $dataCountry = $this->countryService->getForFilterByName();

        return Inertia::render('customer/index', [
            'data' => $data,
            'dataBranch' => $dataBranch,
            'dataSales' => $dataSales,
            'dataCountry' => $dataCountry,
        ]);
    }

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
            'isCustomer' => true,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules($request->role));

        $validated['role'] = 'customer';

        $user = $this->customerUserService->store($validated);

        if (! empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        activity()
            ->performedOn($user)
            ->withProperties(['subject_type' => 'Customer', 'subject_id' => $user->id ?? null])
            ->log('Customer created successfully #'.($user->id ?? null));

        return redirect()->intended(route('customer.index'))->with('success', 'Customer created successfully.');
    }

    public function show(string $id)
    {
        $data = $this->customerUserService->getForEditShow($id);
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
            'isCustomer' => true,
        ]);
    }

    public function getForShow($id)
    {
        return response()->json($this->customerService->getForEditShow($id));
    }

    public function edit(string $id)
    {
        $data = $this->customerUserService->getForEditShow($id);
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
            'isCustomer' => true,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules($request->role, 'update', $id));

        $validated['role'] = 'customer';

        $user = $this->customerUserService->update($validated, $id);

        if (! empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        activity()
            ->performedOn($user)
            ->withProperties(['subject_type' => 'Customer', 'subject_id' => $user->id ?? null])
            ->log('Customer updated successfully #'.($user->id ?? null));

        return redirect()->intended(route('customer.index'))->with('success', 'Customer updated successfully.');
    }

    public function handleCustomer(Request $request, string $id)
    {
        $data = $this->customerService->handleCustomer($request, $id);

        return back()->with('success', "{$data->name} has been successfully assigned to you.");
    }

    public function enableCustomer(string $id)
    {
        $user = User::with('customer')->findOrFail($id);

        if (! $user->customer) {
            return redirect()->back()->with('error', 'Customer record not found.');
        }

        $this->customerService->enableCustomer($user->customer->id);

        return redirect()->intended(route('customer.index'))->with('success', 'Customer enabled successfully.');
    }

    public function disableCustomer(string $id)
    {
        $user = User::with('customer')->findOrFail($id);

        if (! $user->customer) {
            return redirect()->back()->with('error', 'Customer record not found.');
        }

        $this->customerService->disableCustomer($user->customer->id);

        return redirect()->intended(route('customer.index'))->with('success', 'Customer disabled successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->userService->delete($userId);
            }

            return redirect()->intended(route('customer.index'))->with('success', 'Selected customers deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('customer.index'))->with('success', 'Customer deleted successfully.');
    }
}
