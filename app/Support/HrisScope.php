<?php

namespace App\Support;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * HRIS data scope: a user sees everything beneath the org node their role sits at.
 *
 * Two layers:
 *  - ALLOWED  = the maximum the user may ever see (anchor subtree; null = unbounded for
 *    ghosts / full-access roles). This is the switcher's capability set.
 *  - VISIBLE  = the currently active view = the subtree of the user's selected org unit
 *    (org switcher) when set and within ALLOWED, else the full ALLOWED set.
 *
 * Composes with the permission tiers (view-own / view-team / view-all): permission picks the
 * category, the org boundary bounds the "all".
 */
class HrisScope
{
    /**
     * The maximum org-unit ids the user may ever see. null = unbounded.
     *
     * @return array<int, int>|null
     */
    public static function allowedOrgUnitIds(?User $user = null): ?array
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isGhostUser()) {
            return null;
        }

        $anchor = $user->employee?->resolveScopeOrgUnit();

        if ($anchor === null) {
            return [];
        }

        return OrgUnit::subtreeIds((int) $anchor->id);
    }

    /**
     * Org-unit ids visible under the user's active selection. null = unbounded.
     *
     * @return array<int, int>|null
     */
    public static function visibleOrgUnitIds(?User $user = null): ?array
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $allowed = self::allowedOrgUnitIds($user);
        $activeId = $user->selected_org_unit_id;

        if ($activeId !== null && self::withinAllowed((int) $activeId, $allowed)) {
            return OrgUnit::subtreeIds((int) $activeId);
        }

        return $allowed;
    }

    /**
     * Bound a query to the user's visible org-unit subtree. No-op when unbounded.
     */
    public static function apply(Builder $query, string $column = 'org_unit_id', ?User $user = null): Builder
    {
        $ids = self::visibleOrgUnitIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids);
    }

    /**
     * Bound a query through its employee relation (records that roll up to an employee).
     */
    public static function applyViaEmployee(Builder $query, string $relation = 'employee', ?User $user = null): Builder
    {
        $ids = self::visibleOrgUnitIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $q) => $q->whereIn('org_unit_id', $ids));
    }

    /**
     * Whether the user may access (switch to) the given org unit — within ALLOWED.
     */
    public static function canAccess(int $orgUnitId, ?User $user = null): bool
    {
        return self::withinAllowed($orgUnitId, self::allowedOrgUnitIds($user));
    }

    /**
     * Whether the user has no org boundary at all (can switch across everything).
     */
    public static function isUnbounded(?User $user = null): bool
    {
        return self::allowedOrgUnitIds($user) === null;
    }

    /**
     * @param  array<int, int>|null  $allowed
     */
    private static function withinAllowed(int $id, ?array $allowed): bool
    {
        return $allowed === null || in_array($id, $allowed, true);
    }
}
