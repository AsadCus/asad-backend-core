<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\MenuConfigRule;
use App\Services\MenuConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuConfigController extends Controller
{
    public function __construct(
        private MenuConfigService $service,
        private MenuConfigRule $rule,
    ) {}

    /** Effective menu config for the current user (any authenticated role). */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->service->configFor($request->user()));
    }

    /** Replace the global admin overrides. Administrator only. */
    public function updateOverrides(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate($this->rule->overrideRules());
        $this->service->saveOverrides($validated['overrides']);

        return response()->json($this->service->configFor($request->user()));
    }

    /** Upsert the current user's own preferences (favourites / order / hide). */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rule->preferenceRules());
        $this->service->savePreferences($request->user(), $validated['preferences']);

        return response()->json($this->service->configFor($request->user()));
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();

        $canManage = $user?->can('hris.menu manage')
            || $user?->hasAnyRole(['administrator', 'admin', 'superadmin']);

        abort_unless($canManage, 403);
    }
}
