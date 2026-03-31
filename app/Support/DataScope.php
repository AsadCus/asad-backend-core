<?php

namespace App\Support;

use App\Models\User;

class DataScope
{
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

    public static function scopedCountryId(?User $user = null): ?int
    {
        $resolvedUser = $user ?? self::user();

        if ($resolvedUser === null) {
            return null;
        }

        $countryId = (int) ($resolvedUser->branch?->country_id ?? 0);

        return $countryId > 0 ? $countryId : null;
    }
}
