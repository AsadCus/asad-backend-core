<?php

namespace App\Http\Controllers;

use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DataScopeController extends Controller
{
    public function updateCountrySelection(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if(
            $user === null || ! DataScope::hasRole($user, ['admin', 'sales', 'operations']),
            403,
        );

        $assignableCountryIds = DataScope::assignableCountryIds($user);

        if (empty($assignableCountryIds)) {
            return back()->with('error', 'No country scope is assigned to your account.');
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

        $user->forceFill([
            'selected_country_ids' => $countryIds,
        ])->save();

        return back()->with('success', 'Data scope updated successfully.');
    }
}
