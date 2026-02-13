<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppearanceSetting;
use App\Models\Customer;
use App\Models\User;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\CustomerService;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    protected $branchService;

    protected $countryService;

    protected $notificationService;

    protected $customerService;

    public function __construct(BranchService $branchService, CountryService $countryService, NotificationService $notificationService, CustomerService $customerService)
    {
        $this->branchService = $branchService;
        $this->countryService = $countryService;
        $this->notificationService = $notificationService;
        $this->customerService = $customerService;
    }

    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        $dataCountry = $this->countryService->getForFilterByName();
        $dataBranch = $this->branchService->getForFilter();

        return Inertia::render('auth/register', [
            'nationalities' => $dataCountry,
            'branches' => $dataBranch,
            'appearance' => AppearanceSetting::first(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $existingUser = User::withTrashed()->where('email', $request->email)->first();

        if ($existingUser && $existingUser->trashed()) {
            $existingUser->restore();
            $existingUser->update([
                'name' => $request->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $user = $existingUser;
        } else {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
        }

        Customer::updateOrCreate([
            'user_id' => $user->id,
            'age_preferences' => $request->age_preferences,
            'country_preferences' => $request->country_preferences,
            'experience_preferences' => $request->experience_preferences,
            'branch_id' => $request->branch_id,
            'handled_by' => null,
            'last_login' => null,
        ]);

        $user->assignRole(Role::findByName('customer'));

        $this->notificationService->createNotification([
            'title' => 'New Customer Registered',
            'message' => "{$user->name} has just registered as a customer. Do you want to handle it?",
            'type' => 'info',
            'link' => '/customer',
            'exclusive' => false,
        ], [], ['admin', 'sales'], $request->branch_id);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
