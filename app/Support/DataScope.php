<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;

class DataScope
{
    public static function mode(): string
    {
        $mode = strtolower((string) config('data_scope.mode', 'country'));

        return in_array($mode, ['country', 'branch'], true) ? $mode : 'country';
    }

    public static function enabled(): bool
    {
        return (bool) config('data_scope.enabled', true);
    }

    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function hasRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public static function shouldScopeSalesOwnership(?User $user = null): bool
    {
        $resolvedUser = $user ?? self::user();

        return self::enabled()
            && $resolvedUser !== null
            && $resolvedUser->hasRole('sales');
    }

    public static function shouldScopeSalesEnquiries(?User $user = null): bool
    {
        return self::shouldScopeSalesOwnership($user);
    }

    public static function shouldScopePackageAndManifestCountry(?User $user = null): bool
    {
        $resolvedUser = $user ?? self::user();

        return self::enabled()
            && $resolvedUser !== null
            && self::hasRole($resolvedUser, ['admin', 'sales']);
    }

    public static function shouldScopeOpsMovementCountry(?User $user = null): bool
    {
        $resolvedUser = $user ?? self::user();

        return self::enabled()
            && $resolvedUser !== null
            && self::hasRole($resolvedUser, ['admin', 'sales', 'operations']);
    }

    /**
     * @return array<int, int>
     */
    public static function scopedBranchIds(?User $user = null): array
    {
        $resolvedUser = $user ?? self::user();

        if ($resolvedUser === null) {
            return [];
        }

        $scopeSource = self::scopeSource($resolvedUser);

        if ($scopeSource === null) {
            return [];
        }

        $branchIds = self::toIntArray($scopeSource->branch_ids ?? []);
        $primaryBranchId = (int) ($scopeSource->branch_id ?? 0);

        if ($primaryBranchId > 0) {
            $branchIds[] = $primaryBranchId;
        }

        return array_values(array_unique(array_filter($branchIds, static fn (int $id) => $id > 0)));
    }

    /**
     * @return array<int, int>
     */
    public static function scopedCountryIds(?User $user = null): array
    {
        $resolvedUser = $user ?? self::user();

        if ($resolvedUser === null) {
            return [];
        }

        $scopeSource = self::scopeSource($resolvedUser);

        if ($scopeSource === null) {
            return [];
        }

        $countryIds = self::toIntArray($scopeSource->country_ids ?? []);
        $primaryCountryId = (int) ($scopeSource->country_id ?? 0);

        if ($primaryCountryId > 0) {
            $countryIds[] = $primaryCountryId;
        }

        $normalizedCountryIds = array_values(array_unique(array_filter($countryIds, static fn (int $id) => $id > 0)));

        if (self::mode() === 'branch') {
            $scopedBranchIds = self::scopedBranchIds($resolvedUser);

            if (! empty($scopedBranchIds)) {
                $branchCountryIds = Branch::query()
                    ->whereIn('id', $scopedBranchIds)
                    ->pluck('country_id')
                    ->map(static fn ($id) => (int) $id)
                    ->filter(static fn (int $id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $normalizedCountryIds = array_values(array_unique(array_merge($normalizedCountryIds, $branchCountryIds)));
            }
        }

        return $normalizedCountryIds;
    }

    public static function scopedCountryId(?User $user = null): ?int
    {
        $countryIds = self::scopedCountryIds($user);

        return ! empty($countryIds) ? (int) $countryIds[0] : null;
    }

    public static function scopedBranchId(?User $user = null): ?int
    {
        $branchIds = self::scopedBranchIds($user);

        return ! empty($branchIds) ? (int) $branchIds[0] : null;
    }

    private static function scopeSource(User $user): ?object
    {
        if ($user->hasRole('sales')) {
            return $user->sales;
        }

        if ($user->hasRole('admin')) {
            return $user->admin;
        }

        if ($user->hasRole('operations')) {
            return $user->operation;
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private static function toIntArray(mixed $value): array
    {
        $items = is_array($value) ? $value : [];

        return array_values(array_filter(array_map(
            static fn ($item) => (int) $item,
            $items,
        ), static fn (int $id) => $id > 0));
    }
}
