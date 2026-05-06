<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
            && (bool) config('data_scope.sales_ownership', false)
            && $resolvedUser !== null
            && $resolvedUser->hasRole('sales');
    }

    public static function shouldScopeSalesEnquiries(?User $user = null): bool
    {
        return self::shouldScopeSalesOwnership($user);
    }

    public static function shouldScopeEnquiryAndConfirmation(?User $user = null): bool
    {
        $resolvedUser = $user ?? self::user();

        return self::enabled()
            && $resolvedUser !== null
            && self::hasRole($resolvedUser, ['superadmin', 'admin', 'sales', 'operations']);
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

    public static function shouldScopePaymentCreatorCountry(?User $user = null): bool
    {
        $resolvedUser = $user ?? self::user();

        return self::enabled()
            && $resolvedUser !== null
            && self::hasRole($resolvedUser, ['admin', 'sales']);
    }

    /**
     * @return array<int, int>
     */
    public static function selectedCountryIds(?User $user = null): array
    {
        $resolvedUser = $user ?? self::user();

        if ($resolvedUser === null) {
            return [];
        }

        $assignableCountryIds = self::assignableCountryIds($resolvedUser);

        if (empty($assignableCountryIds)) {
            return [];
        }

        return array_values(array_map(
            static fn ($id) => (int) $id,
            array_intersect(
                $assignableCountryIds,
                self::toIntArray($resolvedUser->selected_country_ids ?? []),
            ),
        ));
    }

    public static function applyPaymentCreatorCountryScopeToQuotations(Builder $query, ?User $user = null): Builder
    {
        $resolvedUser = $user ?? self::user();

        if (! self::shouldScopePaymentCreatorCountry($resolvedUser)) {
            return $query;
        }

        $selectedCountryIds = self::selectedCountryIds($resolvedUser);

        return $query->where(function (Builder $scopedQuery) use ($selectedCountryIds): void {
            if (! empty($selectedCountryIds)) {
                $scopedQuery->where(function (Builder $visibleQuery) use ($selectedCountryIds): void {
                    $visibleQuery
                        ->whereHas('createdBy.sales', function (Builder $salesQuery) use ($selectedCountryIds): void {
                            self::applyMatchingCountryConstraint($salesQuery, $selectedCountryIds);
                        })
                        ->orWhereHas('createdBy.admin', function (Builder $adminQuery) use ($selectedCountryIds): void {
                            self::applyMatchingCountryConstraint($adminQuery, $selectedCountryIds);
                        })
                        ->orWhere(function (Builder $globalCreatorQuery): void {
                            self::applyGlobalCreatorConstraint($globalCreatorQuery);
                        });
                });

                return;
            }

            self::applyGlobalCreatorConstraint($scopedQuery);
        });
    }

    public static function applyPaymentCreatorCountryScopeViaQuotationRelation(Builder $query, string $quotationRelation, ?User $user = null): Builder
    {
        $resolvedUser = $user ?? self::user();

        if (! self::shouldScopePaymentCreatorCountry($resolvedUser)) {
            return $query;
        }

        return $query->whereHas($quotationRelation, function (Builder $quotationQuery) use ($resolvedUser): void {
            self::applyPaymentCreatorCountryScopeToQuotations($quotationQuery, $resolvedUser);
        });
    }

    /**
     * @return array<int, int>
     */
    public static function scopedBranchIds(?User $user = null): array
    {
        return self::assignableBranchIds($user);
    }

    /**
     * @return array<int, int>
     */
    public static function assignableBranchIds(?User $user = null): array
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

        $assignableCountryIds = self::assignableCountryIds($resolvedUser);

        if (empty($assignableCountryIds)) {
            return [];
        }

        $selectedCountryIds = self::toIntArray($resolvedUser->selected_country_ids ?? []);
        $selectedCountryIds = array_values(array_map(
            static fn ($id) => (int) $id,
            array_intersect($assignableCountryIds, $selectedCountryIds),
        ));

        return ! empty($selectedCountryIds) ? $selectedCountryIds : $assignableCountryIds;
    }

    /**
     * @return array<int, int>
     */
    public static function assignableCountryIds(?User $user = null): array
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
            $scopedBranchIds = self::assignableBranchIds($resolvedUser);

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

    /**
     * @param  array<int, int>  $countryIds
     */
    private static function applyMatchingCountryConstraint(Builder $query, array $countryIds): void
    {
        $query->where(function (Builder $countryQuery) use ($countryIds): void {
            $countryQuery->whereIn('country_id', $countryIds);

            foreach ($countryIds as $countryId) {
                $countryQuery->orWhereJsonContains('country_ids', (int) $countryId);
            }
        });
    }

    private static function applyGlobalCountryConstraint(Builder $query): void
    {
        $query
            ->whereNull('country_id')
            ->where(function (Builder $countryQuery): void {
                $countryQuery
                    ->whereNull('country_ids')
                    ->orWhereJsonLength('country_ids', 0);
            });
    }

    private static function applyGlobalCreatorConstraint(Builder $query): void
    {
        $query->where(function (Builder $creatorQuery): void {
            $creatorQuery
                ->whereDoesntHave('createdBy.sales')
                ->whereDoesntHave('createdBy.admin');
        })->orWhereHas('createdBy.sales', function (Builder $salesQuery): void {
            self::applyGlobalCountryConstraint($salesQuery);
        })->orWhereHas('createdBy.admin', function (Builder $adminQuery): void {
            self::applyGlobalCountryConstraint($adminQuery);
        });
    }

    private static function scopeSource(User $user): ?object
    {
        if ($user->hasRole('superadmin')) {
            return $user->admin;
        }

        if ($user->hasRole('sales')) {
            return $user->sales;
        }

        if ($user->hasRole('admin')) {
            return $user->sales ?? $user->admin;
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
