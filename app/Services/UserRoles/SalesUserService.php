<?php

namespace App\Services\UserRoles;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SalesUserService
{
    public function __construct(private string $roleName = 'sales') {}

    public function getForDataTable()
    {
        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));

        return User::query()
            ->whereDoesntHave('ghostUser')
            ->role($this->roleName)
            ->with('roles', 'sales.branch.country', 'sales.country')
            ->get()
            ->map(function ($user) use ($scopeMode) {
                $salesScope = $user->sales;
                $branchNames = collect($this->resolveBranchIds($salesScope))
                    ->map(fn (int $id) => Branch::query()->whereKey($id)->value('name'))
                    ->filter()
                    ->values();
                $countryNames = collect($this->resolveCountryIds($salesScope))
                    ->map(fn (int $id) => Country::query()->whereKey($id)->value('name'))
                    ->filter()
                    ->values();

                $user->role = $this->roleName;
                $user->contact = $user->contact ?? '';
                $user->scope_mode = $scopeMode;
                $user->scope_ids = array_map(
                    'strval',
                    $scopeMode === 'branch'
                        ? $this->resolveBranchIds($salesScope)
                        : $this->resolveCountryIds($salesScope),
                );
                $user->branch_id = $salesScope?->branch_id ? (string) $salesScope->branch_id : '';
                $user->branch_name = $branchNames->implode(', ') ?: '-';
                $user->country_name = $countryNames->implode(', ') ?: '-';

                return $user;
            });
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $roleLabel = ucfirst($this->roleName);
            $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
            $scopeIds = $this->normalizeScopeIds($data['scope_ids'] ?? []);
            [$primaryBranchId, $primaryCountryId, $branchIds, $countryIds] =
                $this->resolveScopePayload($scopeIds, $scopeMode);

            $user = $this->createOrRestoreUser($data);

            $user->syncRoles([Role::findByName($this->roleName)]);

            Sales::create([
                'user_id' => $user->id,
                'branch_id' => $primaryBranchId,
                'country_id' => $primaryCountryId,
                'branch_ids' => $branchIds,
                'country_ids' => $countryIds,
            ]);

            $user->forceFill([
                'selected_country_ids' => $countryIds,
            ])->save();

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => $roleLabel.'User', 'subject_id' => $user->id ?? null])
                ->log($roleLabel.'User created successfully #'.($user->id ?? null));

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
        $user = User::role($this->roleName)->with('sales.branch.country', 'sales.country')->findOrFail($id);
        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
        $salesScope = $user->sales;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => $this->roleName,
            'scope_mode' => $scopeMode,
            'scope_ids' => array_map(
                'strval',
                $scopeMode === 'branch'
                    ? $this->resolveBranchIds($salesScope)
                    : $this->resolveCountryIds($salesScope),
            ),
            'country_id' => $salesScope?->country_id ? (string) $salesScope->country_id : '',
            'country_name' => $salesScope?->country?->name ?? '',
            'branch_id' => $salesScope?->branch_id ? (string) $salesScope->branch_id : '',
            'branch_name' => $salesScope?->branch?->name ?? '',
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
            'handled_by' => '',
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $roleLabel = ucfirst($this->roleName);
            $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
            $scopeIds = $this->normalizeScopeIds($data['scope_ids'] ?? []);
            [$primaryBranchId, $primaryCountryId, $branchIds, $countryIds] =
                $this->resolveScopePayload($scopeIds, $scopeMode);

            $user = User::role($this->roleName)->with('sales')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            if ($user->sales) {
                $user->sales->update([
                    'branch_id' => $primaryBranchId,
                    'country_id' => $primaryCountryId,
                    'branch_ids' => $branchIds,
                    'country_ids' => $countryIds,
                ]);
            } else {
                Sales::create([
                    'user_id' => $user->id,
                    'branch_id' => $primaryBranchId,
                    'country_id' => $primaryCountryId,
                    'branch_ids' => $branchIds,
                    'country_ids' => $countryIds,
                ]);
            }

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => $roleLabel.'User', 'subject_id' => $user->id ?? null])
                ->log($roleLabel.'User updated successfully #'.($user->id ?? null));

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
    private function resolveBranchIds(?Sales $salesScope): array
    {
        $branchIds = is_array($salesScope?->branch_ids) ? $salesScope->branch_ids : [];
        $primaryBranchId = (int) ($salesScope?->branch_id ?? 0);

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
    private function resolveCountryIds(?Sales $salesScope): array
    {
        $countryIds = is_array($salesScope?->country_ids) ? $salesScope->country_ids : [];
        $primaryCountryId = (int) ($salesScope?->country_id ?? 0);

        if ($primaryCountryId > 0) {
            $countryIds[] = $primaryCountryId;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $countryIds,
        ), static fn (int $id) => $id > 0)));
    }
}
