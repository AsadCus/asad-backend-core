<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\CustomerService;
use App\Services\SalesService;
use App\Services\UserRoles\CustomerUserService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CustomerController extends Controller
{
    protected $customerUserService;

    protected $userService;

    protected $customerService;

    protected $branchService;

    protected $countryService;

    protected $salesService;

    protected $userRule;

    public function __construct(CustomerUserService $customerUserService, UserService $userService, CustomerService $customerService, BranchService $branchService, CountryService $countryService, SalesService $salesService, UserRule $userRule)
    {
        $this->customerUserService = $customerUserService;
        $this->userService = $userService;
        $this->customerService = $customerService;
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
        $dataUser = $this->customerUserService->getForDataTable();
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/customer/index', [
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
            'isCustomer' => true,
            'submitUrl' => '/master/user/customer',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules('customer'));

        $user = $this->customerUserService->store($validated);

        if (! empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        return redirect()->intended(route('master.user.customer.index'))->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified resource.
     */
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

    /**
     * Show the form for editing the specified resource.
     */
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

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules('customer', 'update', $id));

        $this->customerUserService->update($validated, $id);

        return redirect()->intended(route('master.user.customer.index'))->with('success', 'Customer updated successfully.');
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

            return redirect()->intended(route('master.user.customer.index'))->with('success', 'Selected customers deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('master.user.customer.index'))->with('success', 'Customer deleted successfully.');
    }

    /**
     * Create quotation for selected customer
     */
    public function createQuotation(string $id)
    {
        try {
            // Get user data first
            $user = $this->userService->getForEditShow($id);

            // Get customer data using customer ID from user
            $customer = \App\Models\Customer::where('user_id', $id)->firstOrFail();

            return redirect()->route('quotation.create', [
                'customer_id' => $customer->id,
            ])->with('success', 'Redirected to create quotation for selected customer.');
        } catch (\Exception $e) {
            return back()->with('error', 'Customer not found or unable to create quotation.');
        }
    }
}
