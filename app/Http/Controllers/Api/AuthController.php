<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Country;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\NotificationService;
use App\Support\DataScope;
use App\Support\FeatureFlag;
use App\Support\HrisScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

class AuthController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private NotificationService $notificationService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember')
        )) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $request->user();

        if ($user->employee && ! $user->employee->canLogin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account is inactive. Please contact HR.',
            ]);
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            Auth::logout();
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return response()->json([
                'two_factor' => true,
            ]);
        }

        $request->session()->regenerate();
        $this->customerService->updateLastLogin($user->id);

        activity()->performedOn($user)->log('User logged in');

        return response()->json([
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            activity()->performedOn($user)->log('User logged out');
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    /** Public session probe: the current user, or null for a guest — always 200. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['user' => $user ? $this->userPayload($user) : null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $assigned = $user->employee?->resolveScopeOrgUnit();
        $activeId = $user->selected_org_unit_id;
        $active = ($activeId !== null && HrisScope::canAccess((int) $activeId, $user))
            ? OrgUnit::find($activeId)
            : $assigned;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'signature_url' => $user->signature_url,
            'permissions' => $user->effectivePermissionNames(),
            'roles' => $user->getRoleNames(),
            'can_check_in' => $user->employee ? (bool) $user->employee->can_check_in : false,
            'is_ghost_user' => $user->isGhostUser(),
            'hris_scope' => [
                'anchor_org_unit_id' => $user->employee?->scope_org_unit_id,
                'assigned_org_unit' => $assigned?->toSummary(),
                'active_org_unit' => $active?->toSummary(),
                'unbounded' => HrisScope::isUnbounded($user),
            ],
            'hide_customer_from_user_management' => config('master.hide_customer_from_user_management', false),
            'can_view_documentation' => FeatureFlag::enabled('documentation.visible_to_all_users', $user, false),
            'notifications' => $this->notificationService->getUserNotifications($user->id),
            'scope_mode' => DataScope::mode(),
            'scope_labels' => $this->resolveScopeLabels($user),
            'scope_country_options' => $this->resolveScopeCountryOptions($user),
            'scope_selected_country_ids' => DataScope::scopedCountryIds($user),
        ];
    }

    private function canShowScopeIndicator(User $user): bool
    {
        // HRIS users are position-scoped, not country-scoped — the country indicator
        // stays for the legacy TMS roles only.
        return $user->hasRole('superadmin')
            || $user->hasRole('admin')
            || $user->hasRole('sales')
            || $user->hasRole('operations');
    }

    private function resolveScopeLabels(User $user): array
    {
        if (! $this->canShowScopeIndicator($user)) {
            return [];
        }

        if (DataScope::mode() === 'branch') {
            $branchIds = DataScope::scopedBranchIds($user);
            if (empty($branchIds)) {
                return [];
            }
            $names = Branch::query()->whereIn('id', $branchIds)->pluck('name', 'id');
            $labels = [];
            foreach ($branchIds as $id) {
                $label = $names->get($id);
                if (is_string($label) && $label !== '') {
                    $labels[] = $label;
                }
            }

            return array_values(array_unique($labels));
        }

        $countryIds = DataScope::assignableCountryIds($user);
        if (empty($countryIds)) {
            return [];
        }
        $names = Country::query()->whereIn('id', $countryIds)->pluck('name', 'id');
        $labels = [];
        foreach ($countryIds as $id) {
            $label = $names->get($id);
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    private function resolveScopeCountryOptions(User $user): array
    {
        if (! $this->canShowScopeIndicator($user) || DataScope::mode() !== 'country') {
            return [];
        }

        $countryIds = DataScope::assignableCountryIds($user);
        if (empty($countryIds)) {
            return [];
        }

        $names = Country::query()->whereIn('id', $countryIds)->pluck('name', 'id');
        $options = [];
        foreach ($countryIds as $id) {
            $label = $names->get($id);
            if (! is_string($label) || $label === '') {
                continue;
            }
            $options[] = ['id' => $id, 'label' => $label];
        }

        return $options;
    }
}
