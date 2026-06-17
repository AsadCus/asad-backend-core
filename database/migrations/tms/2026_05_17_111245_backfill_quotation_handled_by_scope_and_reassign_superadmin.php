<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data-only backfill: fills `country_id` / `branch_id` from the user's
     * Sales / Admin scope, then re-assigns any quotation owned by a
     * superadmin to a random sales/admin user inside the matching scope.
     */
    public function up(): void
    {
        $resolveFirstId = static function ($value): ?int {
            if (is_string($value)) {
                $value = json_decode($value, true);
            }

            if (! is_array($value)) {
                return null;
            }

            foreach ($value as $item) {
                $id = (int) $item;

                if ($id > 0) {
                    return $id;
                }
            }

            return null;
        };

        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));

        $superadminUserIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', 'superadmin')
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $superadminLookup = array_flip($superadminUserIds);

        $assignableRoleIds = DB::table('roles')
            ->whereIn('name', ['sales', 'admin'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::table('quotations')
            ->whereNotNull('handled_by')
            ->orderBy('id')
            ->chunkById(200, function ($quotations) use ($resolveFirstId, $superadminLookup, $assignableRoleIds, $scopeMode): void {
                $userIds = $quotations
                    ->pluck('handled_by')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($userIds)) {
                    return;
                }

                $salesScopes = DB::table('sales')
                    ->whereIn('user_id', $userIds)
                    ->get()
                    ->keyBy('user_id');

                $adminScopes = DB::table('admins')
                    ->whereIn('user_id', $userIds)
                    ->get()
                    ->keyBy('user_id');

                foreach ($quotations as $quotation) {
                    $userId = (int) ($quotation->handled_by ?? 0);

                    if ($userId <= 0) {
                        continue;
                    }

                    $scope = $salesScopes->get($userId) ?? $adminScopes->get($userId);

                    $countryId = (int) ($quotation->country_id ?? 0);
                    $branchId = (int) ($quotation->branch_id ?? 0);

                    if ($scope) {
                        if ($countryId <= 0) {
                            $countryId = (int) ($scope->country_id ?? 0);

                            if ($countryId <= 0) {
                                $countryId = (int) ($resolveFirstId($scope->country_ids ?? null) ?? 0);
                            }
                        }

                        if ($branchId <= 0) {
                            $branchId = (int) ($scope->branch_id ?? 0);

                            if ($branchId <= 0) {
                                $branchId = (int) ($resolveFirstId($scope->branch_ids ?? null) ?? 0);
                            }
                        }

                        if ($countryId > 0 || $branchId > 0) {
                            DB::table('quotations')
                                ->where('id', $quotation->id)
                                ->update([
                                    'country_id' => $countryId > 0 ? $countryId : null,
                                    'branch_id' => $branchId > 0 ? $branchId : null,
                                ]);
                        }
                    }

                    if (! isset($superadminLookup[$userId])) {
                        continue;
                    }

                    $replacementId = $this->pickRandomScopedUserId(
                        $assignableRoleIds,
                        $scopeMode,
                        $countryId > 0 ? $countryId : null,
                        $branchId > 0 ? $branchId : null,
                    );

                    DB::table('quotations')
                        ->where('id', $quotation->id)
                        ->update(['handled_by' => $replacementId]);
                }
            });
    }

    public function down(): void
    {
        // No schema changes here; pure data backfill.
    }

    /**
     * @param  array<int, int>  $assignableRoleIds
     */
    private function pickRandomScopedUserId(array $assignableRoleIds, string $scopeMode, ?int $countryId, ?int $branchId): ?int
    {
        if (empty($assignableRoleIds)) {
            return null;
        }

        $scopeColumn = $scopeMode === 'branch' ? 'branch_id' : 'country_id';
        $scopeIdsColumn = $scopeMode === 'branch' ? 'branch_ids' : 'country_ids';
        $matchId = $scopeMode === 'branch' ? $branchId : $countryId;

        if ($matchId === null || $matchId <= 0) {
            return null;
        }

        $jsonNeedle = json_encode($matchId);

        $matchingFromTable = function (string $table) use ($scopeColumn, $scopeIdsColumn, $matchId, $jsonNeedle) {
            return DB::table($table)
                ->where(function ($query) use ($scopeColumn, $scopeIdsColumn, $matchId, $jsonNeedle): void {
                    $query
                        ->where($scopeColumn, $matchId)
                        ->orWhereRaw("JSON_CONTAINS({$scopeIdsColumn}, ?)", [$jsonNeedle]);
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        };

        $matchingUserIds = array_values(array_unique(array_merge(
            $matchingFromTable('sales'),
            $matchingFromTable('admins'),
        )));

        if (empty($matchingUserIds)) {
            return null;
        }

        $eligibleUserIds = DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.role_id', $assignableRoleIds)
            ->whereNull('users.deleted_at')
            ->whereIn('users.id', $matchingUserIds)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($eligibleUserIds)) {
            return null;
        }

        return $eligibleUserIds[array_rand($eligibleUserIds)];
    }
};
