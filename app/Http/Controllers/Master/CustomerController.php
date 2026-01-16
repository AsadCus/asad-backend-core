<?php

namespace App\Http\Controllers\Master;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\CustomerService;

class CustomerController extends Controller
{
    protected $userService, $customerService;

    public function __construct(UserService $userService, CustomerService $customerService)
    {
        $this->userService = $userService;
        $this->customerService = $customerService;
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
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
