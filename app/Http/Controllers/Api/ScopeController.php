<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use App\Support\HrisScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScopeController extends Controller
{
    /**
     * Org units the user may switch the active scope to (their allowed subtree;
     * all units when unbounded). Powers the sidebar org switcher.
     */
    public function orgUnits(): JsonResponse
    {
        $allowed = HrisScope::allowedOrgUnitIds();

        $query = OrgUnit::query()->orderBy('sort_order')->orderBy('name');

        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return response()->json($query->get()->map(fn (OrgUnit $unit) => $unit->toSummary()));
    }

    /**
     * Set or clear the active org unit. Narrowing only — selecting outside the user's
     * allowed scope is rejected (no escalation). Null clears it (back to the anchor).
     */
    public function setOrgUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'org_unit_id' => ['nullable', 'integer', 'exists:org_units,id'],
        ]);

        $user = $request->user();
        $id = $validated['org_unit_id'] ?? null;

        if ($id !== null && ! HrisScope::canAccess((int) $id, $user)) {
            throw ValidationException::withMessages([
                'org_unit_id' => ['That organization unit is outside your allowed scope.'],
            ]);
        }

        $user->forceFill(['selected_org_unit_id' => $id])->save();

        return response()->json([
            'status' => 'ok',
            'active_org_unit' => $id !== null ? OrgUnit::find($id)?->toSummary() : null,
        ]);
    }
}
