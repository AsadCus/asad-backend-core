<?php

namespace App\Http\Controllers\Master;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\CustomerService;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\SalesService;
use App\Rules\UserRule;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;

class CustomerController extends Controller
{
    protected $userService, $customerService, $branchService, $countryService, $salesService, $userRule;

    public function __construct(UserService $userService, CustomerService $customerService, BranchService $branchService, CountryService $countryService, SalesService $salesService, UserRule $userRule)
    {
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
        $dataUser = $this->userService->getForDataTable('customer');

        return Inertia::render('masters/users/customer/index', [
            'dataUser' => $dataUser,
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

        $user = $this->userService->store($validated);

        if (!empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        $this->customerService->getInitialCustomerMaidIds($user->id);

        return redirect()->intended(route('master.user.customer.index'))->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
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
                'customer_id' => $customer->id
            ])->with('success', 'Redirected to create quotation for selected customer.');
        } catch (\Exception $e) {
            return back()->with('error', 'Customer not found or unable to create quotation.');
        }
    }
}
