<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DataScopeController extends Controller
{
    public function updateCountries(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(
            $user === null || ! DataScope::hasRole($user, ['superadmin', 'admin', 'sales', 'operations']),
            403,
        );

        $assignableCountryIds = DataScope::assignableCountryIds($user);

        if (empty($assignableCountryIds)) {
            throw ValidationException::withMessages([
                'country_ids' => ['No country scope is assigned to your account.'],
            ]);
        }

        $validated = $request->validate([
            'country_ids' => ['required', 'array', 'min:1'],
            'country_ids.*' => ['required', 'integer', Rule::in($assignableCountryIds)],
        ], [
            'country_ids.required' => 'Please select at least one country.',
            'country_ids.array' => 'Please select at least one country.',
            'country_ids.min' => 'Please select at least one country.',
            'country_ids.*.in' => 'One or more selected countries are outside your assigned scope.',
        ]);

        $countryIds = array_values(array_unique(array_map(
            static fn ($id) => (int) $id,
            $validated['country_ids'],
        )));

        $user->forceFill(['selected_country_ids' => $countryIds])->save();

        return response()->json([
            'status' => 'ok',
            'selected_country_ids' => $countryIds,
        ]);
    }
}
