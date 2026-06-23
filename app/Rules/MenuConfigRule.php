<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class MenuConfigRule
{
    /** Validation for a bulk admin override payload: { overrides: [ {menu_key, ...}, ... ] }. */
    public function overrideRules(): array
    {
        return [
            'overrides' => ['present', 'array'],
            'overrides.*.menu_key' => ['required', 'string', 'max:255'],
            'overrides.*.label' => ['nullable', 'string', 'max:255'],
            // Lucide icon name. Kept as a free string: the frontend icon picker constrains the
            // choices and an unknown name just renders without an icon. ponytail: no synced enum.
            'overrides.*.icon' => ['nullable', 'string', 'max:64'],
            'overrides.*.zone' => ['nullable', 'string', 'max:64'],
            'overrides.*.sort_order' => ['nullable', 'integer'],
            'overrides.*.is_hidden' => ['boolean'],
            // Single permission string that gates this menu (null = use the registry permission).
            'overrides.*.permission' => ['nullable', 'string', Rule::exists('permissions', 'name')],
        ];
    }

    /** Validation for a bulk per-user preference payload: { preferences: [ {menu_key, ...}, ... ] }. */
    public function preferenceRules(): array
    {
        return [
            'preferences' => ['present', 'array'],
            'preferences.*.menu_key' => ['required', 'string', 'max:255'],
            'preferences.*.is_favorite' => ['nullable', 'boolean'],
            'preferences.*.is_hidden' => ['nullable', 'boolean'],
            'preferences.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
