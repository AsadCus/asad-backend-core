<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Rules\UserRule;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\SalesService;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\CustomerService;
use App\Services\EducationLevelService;
use App\Services\MaidService;
use App\Services\ReligionService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

class CustomerController extends Controller
{
    protected $customerService, $branchService, $salesService, $countryService, $userService, $userRule, $religionService, $educationLevelService, $maidService;

    public function __construct(
        CustomerService $customerService,
        BranchService $branchService,
        SalesService $salesService,
        CountryService $countryService,
        UserService $userService,
        UserRule $userRule,
        ReligionService $religionService,
        EducationLevelService $educationLevelService,
        MaidService $maidService,
    ) {
        $this->customerService = $customerService;
        $this->branchService = $branchService;
        $this->salesService = $salesService;
        $this->countryService = $countryService;
        $this->userService = $userService;
        $this->userRule = $userRule;
        $this->religionService = $religionService;
        $this->educationLevelService = $educationLevelService;
        $this->maidService = $maidService;

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
            'isCustomer' => true
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules($request->role));

        $user = $this->userService->store($validated);

        if (!empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        $this->customerService->getInitialCustomerMaidIds($user->id);

        return redirect()->intended(route('customer.index'))->with('success', 'Customer created successfully.');
    }

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
            'isCustomer' => true
        ]);
    }

    public function getForShow($id)
    {
        return response()->json($this->customerService->getForEditShow($id));
    }

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
            'isCustomer' => true
        ]);
    }

    public function editRecommendMaid(string $id)
    {
        $data['user'] = $this->userService->getForEditShow($id);
        $data['maids'] = $this->maidService->getForRecommendation();
        $data['selectedMaidIds'] = $this->customerService->getCustomerMaidIds($id);
        $data['nationality'] = $this->countryService->getForFilterByAdjective();
        $data['religion'] = $this->religionService->getForFilterByName();
        $data['educationLevel'] = $this->educationLevelService->getForFilterByName();
        $data['roles'] = $this->userService->getRoleForFilter();
        $data['branches'] = $this->branchService->getForFilter();
        $data['countries'] = $this->countryService->getForFilterByName();
        $data['sales'] = $this->salesService->getForFilter();

        return Inertia::render('customer/recommend-maid-form', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules($request->role, 'update', $id));

        $user = $this->userService->update($validated, $id);

        if (!empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        $this->customerService->getInitialCustomerMaidIds($user->id);

        return redirect()->intended(route('customer.index'))->with('success', 'Customer updated successfully.');
    }

    public function handleCustomer(Request $request, string $id)
    {
        $data = $this->customerService->handleCustomer($request, $id);

        return back()->with('success', "{$data->name} has been successfully assigned to you.");
    }

    public function submitRecommendMaid(Request $request, $id)
    {
        $validated = $request->validate([
            'maids' => 'required|array',
            'maids.*' => 'exists:maids,id',
        ]);

        $result = $this->customerService->storeRecommendMaid($id, $validated['maids']);

        return redirect()->intended(route('customer.index'))->with('success', $result['message']);
    }

    public function assignMaidToCustomer(Request $request, $customerId, $maidId)
    {
        $user = User::with('customer')->findOrFail($customerId);
        $actualCustomerId = $user->customer->id;

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'customer_id' => $actualCustomerId,
                'maid_id' => $maidId,
                'message' => 'Redirected to create quotation for customer and maid assignment.'
            ]);
        }

        return redirect()->route('quotation.create', [
            'customer_id' => $actualCustomerId,
            'maid_id' => $maidId
        ])->with('success', 'Redirected to create quotation for customer and maid assignment.');
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
