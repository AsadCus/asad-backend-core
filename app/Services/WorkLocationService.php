<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OrgUnit;

class WorkLocationService
{
    /**
     * All employees with their resolved work-location info, for the admin governance table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(): array
    {
        return Employee::query()
            ->with([
                'user.roles',
                'orgUnit.parent.parent.parent.parent',
                'workLocation.parent.parent.parent.parent',
            ])
            ->orderBy('employee_no')
            ->get()
            ->map(function (Employee $e) {
                $resolved = $e->resolveWorkLocation();

                return [
                    'id' => $e->id,
                    'employee_no' => $e->employee_no,
                    'name' => $e->user?->name ?? $e->employee_no,
                    'role' => $e->user?->getRoleNames()->first(),
                    'org_unit_id' => $e->org_unit_id,
                    'org_unit' => $e->orgUnit?->name,
                    'holding' => $this->resolveHolding($e->orgUnit),
                    'work_location_org_unit_id' => $e->work_location_org_unit_id,
                    'work_location_override' => $e->workLocation?->name,
                    'work_location_resolved' => $resolved?->name,
                    'has_geofence' => $resolved !== null
                        && $resolved->has_location === true
                        && $resolved->latitude !== null
                        && $resolved->geofence_radius_meters > 0,
                    'geofence_radius_meters' => ($resolved?->has_location) ? $resolved->geofence_radius_meters : null,
                ];
            })
            ->all();
    }

    /**
     * Walk up parent chain to find the root (holding) org unit name.
     */
    private function resolveHolding(?OrgUnit $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        $node = $unit;
        while ($node->parent !== null) {
            $node = $node->parent;
        }

        return $node->name;
    }

    /**
     * All org units that have has_location = true, as select options.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLocationOptions(): array
    {
        return OrgUnit::query()
            ->where('has_location', true)
            ->where(function ($q) {
                $q->whereNotNull('latitude')->whereNotNull('longitude');
            })
            ->orderBy('name')
            ->get()
            ->map(fn (OrgUnit $u) => [
                'value' => $u->id,
                'label' => $u->name,
                'radius' => $u->geofence_radius_meters,
            ])
            ->all();
    }

    /**
     * Assign (or clear) an explicit work-location override for one employee.
     *
     * @return array<string, mixed>
     */
    public function setLocation(int $employeeId, ?int $orgUnitId): array
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->update(['work_location_org_unit_id' => $orgUnitId]);
        $employee->refresh()->load(['workLocation', 'orgUnit.parent.parent.parent.parent']);

        $resolved = $employee->resolveWorkLocation();

        activity()->performedOn($employee)
            ->log('Work location '.($orgUnitId ? 'set to org_unit #'.$orgUnitId : 'cleared'));

        return [
            'id' => $employee->id,
            'holding' => $this->resolveHolding($employee->orgUnit),
            'work_location_org_unit_id' => $employee->work_location_org_unit_id,
            'work_location_override' => $employee->workLocation?->name,
            'work_location_resolved' => $resolved?->name,
            'has_geofence' => $resolved !== null
                && $resolved->has_location === true
                && $resolved->latitude !== null
                && $resolved->geofence_radius_meters > 0,
            'geofence_radius_meters' => ($resolved?->has_location) ? $resolved->geofence_radius_meters : null,
        ];
    }

    /**
     * Assign a location to many employees at once.
     *
     * @param  array<int>  $ids
     */
    public function bulkSetLocation(array $ids, ?int $orgUnitId): int
    {
        return Employee::query()
            ->whereIn('id', $ids)
            ->update(['work_location_org_unit_id' => $orgUnitId]);
    }
}
