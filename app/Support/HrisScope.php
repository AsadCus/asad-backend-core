<?php

namespace App\Support;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * HRIS data scope: a user sees everything beneath the org node their role sits at.
 *
 * Resolution is the subtree of the employee's scope anchor (scope_org_unit_id, else their
 * own branch — least privilege). Ghosts and full-access roles are unbounded. This composes
 * with the permission tiers (view-own / view-team / view-all): permission picks the category,
 * the anchor bounds the "all".
 */
class HrisScope
{
    /**
     * Org-unit ids the user may see. null = unbounded (no boundary).
     *
     * @return array<int, int>|null
     */
    public static function visibleOrgUnitIds(?User $user = null): ?array
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        // Ghosts and full-access roles see the whole organisation.
        if ($user->isGhostUser() || $user->hasFullAccessRole()) {
            return null;
        }

        $anchor = $user->employee?->resolveScopeOrgUnit();

        if ($anchor === null) {
            return []; // no anchor → least privilege (sees nothing org-wide)
        }

        return OrgUnit::subtreeIds((int) $anchor->id);
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
     * Whether the user has no org boundary (sees everything).
     */
    public static function isUnbounded(?User $user = null): bool
    {
        return self::visibleOrgUnitIds($user) === null;
    }
}
