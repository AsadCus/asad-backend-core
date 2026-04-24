<?php

namespace App\Services\UserRoles;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class OperationsUserService
{
    public function getForDataTable()
    {
        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));

        return User::query()
            ->whereDoesntHave('ghostUser')
            ->role('operations')
            ->with('roles', 'operation.branch.country', 'operation.country')
            ->get()
            ->map(function ($user) use ($scopeMode) {
                $operationScope = $user->operation;

                $branchNames = collect($this->resolveBranchIds($operationScope))
                    ->map(fn (int $id) => Branch::query()->whereKey($id)->value('name'))
                    ->filter()
                    ->values();

                $countryNames = collect($this->resolveCountryIds($operationScope))
                    ->map(fn (int $id) => Country::query()->whereKey($id)->value('name'))
                    ->filter()
                    ->values();

                $user->role = 'operations';
                $user->contact = $user->contact ?? '';
                $user->scope_mode = $scopeMode;
                $user->scope_ids = array_map(
                    'strval',
                    $scopeMode === 'branch'
                        ? $this->resolveBranchIds($operationScope)
                        : $this->resolveCountryIds($operationScope),
                );
                $user->branch_name = $branchNames->implode(', ') ?: '-';
                $user->country_name = $countryNames->implode(', ') ?: '-';
                $user->branch_id = $operationScope?->branch_id ? (string) $operationScope->branch_id : '';

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

            $user->syncRoles([Role::findByName('operations')]);

            Operation::updateOrCreate([
                'user_id' => $user->id,
            ], [
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
                ->withProperties(['subject_type' => 'OperationsUser', 'subject_id' => $user->id ?? null])
                ->log('OperationsUser created successfully #'.($user->id ?? null));

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
        $user = User::role('operations')->with('operation.branch.country', 'operation.country')->findOrFail($id);
        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));
        $operationScope = $user->operation;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => 'operations',
            'scope_mode' => $scopeMode,
            'scope_ids' => array_map(
                'strval',
                $scopeMode === 'branch'
                    ? $this->resolveBranchIds($operationScope)
                    : $this->resolveCountryIds($operationScope),
            ),
            'country_id' => $operationScope?->country_id ? (string) $operationScope->country_id : '',
            'country_name' => $operationScope?->country?->name ?? '',
            'branch_id' => $operationScope?->branch_id ? (string) $operationScope->branch_id : '',
            'branch_name' => $operationScope?->branch?->name ?? '',
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

            $user = User::role('operations')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            Operation::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'branch_id' => $primaryBranchId,
                'country_id' => $primaryCountryId,
                'branch_ids' => $branchIds,
                'country_ids' => $countryIds,
            ]);

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'OperationsUser', 'subject_id' => $user->id ?? null])
                ->log('OperationsUser updated successfully #'.($user->id ?? null));

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
    private function resolveBranchIds(?Operation $operationScope): array
    {
        $branchIds = is_array($operationScope?->branch_ids) ? $operationScope->branch_ids : [];
        $primaryBranchId = (int) ($operationScope?->branch_id ?? 0);

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
    private function resolveCountryIds(?Operation $operationScope): array
    {
        $countryIds = is_array($operationScope?->country_ids) ? $operationScope->country_ids : [];
        $primaryCountryId = (int) ($operationScope?->country_id ?? 0);

        if ($primaryCountryId > 0) {
            $countryIds[] = $primaryCountryId;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $countryIds,
        ), static fn (int $id) => $id > 0)));
    }
}
