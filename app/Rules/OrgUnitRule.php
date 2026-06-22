<?php

namespace App\Rules;

use App\Enums\OrgUnitType;
use Illuminate\Validation\Rule;

class OrgUnitRule
{
    public function rules(?string $id = null): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:org_units,id'],
            'type' => ['required', Rule::in(OrgUnitType::values())],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('org_units', 'code')->ignore($id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
