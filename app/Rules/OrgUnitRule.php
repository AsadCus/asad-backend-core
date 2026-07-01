<?php

namespace App\Rules;

use App\Enums\OrgUnitType;
use App\Support\AttendancePeriod;
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
            'logo' => ['nullable', 'image', 'max:5120'],
            'default_work_schedule_id' => ['nullable', 'integer', 'exists:work_schedules,id'],
            // Day-of-month the attendance/payroll period starts (1 = a plain calendar
            // month). Left blank to inherit from the nearest configured ancestor.
            'attendance_cutoff_day' => [
                'nullable', 'integer',
                'between:'.AttendancePeriod::MIN_CUTOFF_DAY.','.AttendancePeriod::MAX_CUTOFF_DAY,
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            // Any unit type can be a physical place; coordinates are required once it is one.
            'has_location' => ['boolean'],
            'latitude' => ['nullable', 'required_if:has_location,1,true', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:has_location,1,true', 'numeric', 'between:-180,180'],
            // Required once located — an unbounded geofence would let check-in succeed from anywhere.
            'geofence_radius_meters' => ['nullable', 'required_if:has_location,1,true', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }
}
