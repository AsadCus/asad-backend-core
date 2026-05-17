<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Country;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\DataScope;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user?->load(['roles.permissions', 'sales', 'admin', 'operation']),
                'roles' => $user?->getRoleNames(),
                'permissions' => $user?->getAllPermissions()->pluck('name'),
                'is_ghost_user' => $user?->isGhostUser() ?? false,
                'hide_customer_from_user_management' => config('master.hide_customer_from_user_management', false),
                'can_view_documentation' => (bool) config('documentation.visible_to_all_users') || ($user?->isGhostUser() ?? false),
                'notifications' => $user
                    ? $this->notificationService->getUserNotifications($user->id)
                    : [],
                'scope_mode' => DataScope::mode(),
                'scope_labels' => $user ? $this->resolveScopeLabels($user) : [],
                'scope_country_options' => $user ? $this->resolveScopeCountryOptions($user) : [],
                'scope_selected_country_ids' => $user ? DataScope::scopedCountryIds($user) : [],
                'scope_selected_branch_ids' => $user ? DataScope::scopedBranchIds($user) : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'result' => fn () => $request->session()->get('result'),
            ],

        ];
    }

    /**
     * @return array<int, string>
     */
    protected function resolveScopeLabels(User $user): array
    {
        if (! $this->canShowScopeIndicator($user)) {
            return [];
        }

        if (DataScope::mode() === 'branch') {
            $branchIds = DataScope::scopedBranchIds($user);

            if (empty($branchIds)) {
                return [];
            }

            $branchNamesById = Branch::query()
                ->whereIn('id', $branchIds)
                ->pluck('name', 'id');

            $labels = [];
            foreach ($branchIds as $branchId) {
                $label = $branchNamesById->get($branchId);
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

        $countryNamesById = Country::query()
            ->whereIn('id', $countryIds)
            ->pluck('name', 'id');

        $labels = [];
        foreach ($countryIds as $countryId) {
            $label = $countryNamesById->get($countryId);
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    protected function resolveScopeCountryOptions(User $user): array
    {
        if (! $this->canShowScopeIndicator($user) || DataScope::mode() !== 'country') {
            return [];
        }

        $countryIds = DataScope::assignableCountryIds($user);

        if (empty($countryIds)) {
            return [];
        }

        $countryNamesById = Country::query()
            ->whereIn('id', $countryIds)
            ->pluck('name', 'id');

        $options = [];
        foreach ($countryIds as $countryId) {
            $label = $countryNamesById->get($countryId);
            if (! is_string($label) || $label === '') {
                continue;
            }

            $options[] = [
                'id' => $countryId,
                'label' => $label,
            ];
        }

        return $options;
    }

    protected function canShowScopeIndicator(User $user): bool
    {
        return $user->hasRole('superadmin')
            || $user->hasRole('admin')
            || $user->hasRole('sales')
            || $user->hasRole('operations');
    }
}
