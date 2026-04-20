<?php

namespace App\Services\UserRoles;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserService
{
    public function getForDataTable()
    {
        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
        $viewer = auth()->user();
        $isGhostAdminViewer =
            $viewer !== null &&
            $viewer->hasRole('admin') &&
            $viewer->isGhostUser();

        return User::query()
            ->when(
                ! $isGhostAdminViewer,
                fn ($query) => $query->whereDoesntHave('ghostUser'),
                fn ($query) => $query->where(function ($visibilityQuery) use ($viewer) {
                    $visibilityQuery
                        ->whereDoesntHave('ghostUser')
                        ->orWhere('id', (int) ($viewer?->id ?? 0));
                }),
            )
            ->role('admin')
            ->with('roles', 'admin.branch.country', 'admin.country')
            ->get()
            ->map(function ($user) use ($scopeMode) {
                $adminScope = $user->admin;
                $branchNames = collect($this->resolveBranchIds($adminScope))
                    ->map(fn (int $id) => Branch::query()->whereKey($id)->value('name'))
                    ->filter()
                    ->values();

                $user->role = 'admin';
                $user->contact = $user->contact ?? '';
                $user->scope_mode = $scopeMode;
                $user->scope_ids = array_map(
                    'strval',
                    $scopeMode === 'branch'
                        ? $this->resolveBranchIds($adminScope)
                        : $this->resolveCountryIds($adminScope),
                );
                $user->branch_name = $branchNames->implode(', ') ?: '-';
                $user->country_name = $this->resolveCountryNameForListing($adminScope);
                $user->branch_id = $adminScope?->branch_id ? (string) $adminScope->branch_id : '';

                return $user;
            });
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
            $scopeIds = $this->normalizeScopeIds($data['scope_ids'] ?? []);

            [$primaryBranchId, $primaryCountryId, $branchIds, $countryIds] =
                $this->resolveScopePayload($scopeIds, $scopeMode);

            $user = $this->createOrRestoreUser($data);

            $user->syncRoles([Role::findByName('admin')]);

            Admin::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'branch_id' => $primaryBranchId,
                'country_id' => $primaryCountryId,
                'branch_ids' => $branchIds,
                'country_ids' => $countryIds,
            ]);

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'AdminUser', 'subject_id' => $user->id ?? null])
                ->log('AdminUser created successfully #'.($user->id ?? null));

            return $user;
        });
    }

    private function createOrRestoreUser(array $data): User
    {
        $hashedPassword = isset($data['password']) && $data['password']
            ? Hash::make((string) $data['password'])
            : Hash::make('password');

        $existingUser = User::withTrashed()
            ->where('email', (string) $data['email'])
            ->first();

        if ($existingUser && $existingUser->trashed()) {
            $existingUser->restore();
            $existingUser->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => $hashedPassword,
            ]);

            return $existingUser->fresh();
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'contact' => $data['contact'] ?? null,
            'password' => $hashedPassword,
        ]);
    }

    public function getForEditShow($id)
    {
        $user = User::role('admin')->with('admin.branch.country', 'admin.country')->findOrFail($id);
        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
        $adminScope = $user->admin;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => 'admin',
            'scope_mode' => $scopeMode,
            'scope_ids' => array_map(
                'strval',
                $scopeMode === 'branch'
                    ? $this->resolveBranchIds($adminScope)
                    : $this->resolveCountryIds($adminScope),
            ),
            'country_id' => $adminScope?->country_id ? (string) $adminScope->country_id : '',
            'country_name' => $adminScope?->country?->name ?? '',
            'branch_id' => $adminScope?->branch_id ? (string) $adminScope->branch_id : '',
            'branch_name' => $adminScope?->branch?->name ?? '',
            'company_name' => '',
            'customer_id' => null,
            'customer_number' => '',
            'nric_number' => '',
            'address' => '',
            'nationality' => '',
            'passport_number' => '',
            'passport_issue_date' => '',
            'passport_expiry_date' => '',
            'passport_place_of_issue' => '',
            'gender' => '',
            'marital_status' => '',
            'date_of_birth' => '',
            'place_of_birth' => '',
            'first_time_umrah' => false,
            'has_chronic_disease' => false,
            'chronic_disease_details' => '',
            'passport_path' => null,
            'photo_path' => null,
            'handled_by' => '',
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
            $scopeIds = $this->normalizeScopeIds($data['scope_ids'] ?? []);
            [$primaryBranchId, $primaryCountryId, $branchIds, $countryIds] =
                $this->resolveScopePayload($scopeIds, $scopeMode);

            $user = User::role('admin')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            Admin::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'branch_id' => $primaryBranchId,
                'country_id' => $primaryCountryId,
                'branch_ids' => $branchIds,
                'country_ids' => $countryIds,
            ]);

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'AdminUser', 'subject_id' => $user->id ?? null])
                ->log('AdminUser updated successfully #'.($user->id ?? null));

            return $user;
        });
    }

    /**
     * @return array<int, int>
     */
    private function normalizeScopeIds(mixed $scopeIds): array
    {
        if (! is_array($scopeIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $scopeIds,
        ), static fn (int $id) => $id > 0)));
    }

    /**
     * @return array{0:int|null,1:int|null,2:array<int,int>,3:array<int,int>}
     */
    private function resolveScopePayload(array $scopeIds, string $scopeMode): array
    {
        if ($scopeMode === 'branch') {
            $branchIds = $scopeIds;
            $countryIds = Branch::query()
                ->whereIn('id', $branchIds)
                ->pluck('country_id')
                ->map(fn ($countryId) => (int) $countryId)
                ->filter(fn (int $countryId) => $countryId > 0)
                ->unique()
                ->values()
                ->all();

            return [
                ! empty($branchIds) ? $branchIds[0] : null,
                ! empty($countryIds) ? $countryIds[0] : null,
                $branchIds,
                $countryIds,
            ];
        }

        $countryIds = $scopeIds;

        return [
            null,
            ! empty($countryIds) ? $countryIds[0] : null,
            [],
            $countryIds,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function resolveBranchIds(?Admin $adminScope): array
    {
        $branchIds = is_array($adminScope?->branch_ids) ? $adminScope->branch_ids : [];
        $primaryBranchId = (int) ($adminScope?->branch_id ?? 0);

        if ($primaryBranchId > 0) {
            $branchIds[] = $primaryBranchId;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $branchIds,
        ), static fn (int $id) => $id > 0)));
    }

    /**
     * @return array<int, int>
     */
    private function resolveCountryIds(?Admin $adminScope): array
    {
        $countryIds = is_array($adminScope?->country_ids) ? $adminScope->country_ids : [];
        $primaryCountryId = (int) ($adminScope?->country_id ?? 0);

        if ($primaryCountryId > 0) {
            $countryIds[] = $primaryCountryId;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $countryIds,
        ), static fn (int $id) => $id > 0)));
    }

    private function resolveCountryNameForListing(?Admin $adminScope): string
    {
        $countryNames = collect($this->resolveCountryIds($adminScope))
            ->map(fn (int $id) => $id > 0 ? \App\Models\Country::query()->whereKey($id)->value('name') : null)
            ->filter()
            ->values();

        return $countryNames->implode(', ') ?: '-';
    }
}
