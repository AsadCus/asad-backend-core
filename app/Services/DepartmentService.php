<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    public function getForDataTable()
    {
        return Department::query()->with('businessUnit')->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'business_unit_id' => $q->business_unit_id,
            'business_unit_name' => $q->businessUnit?->name,
            'is_active' => (bool) $q->is_active,
        ]);
    }

    public function getForFilter()
    {
        return Department::query()->orderBy('name')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
        ]);
    }

    public function getForEditShow($id)
    {
        $department = Department::findOrFail($id);

        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'business_unit_id' => $department->business_unit_id,
            'is_active' => (bool) $department->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $department = Department::create($data);

            activity()->performedOn($department)->log('Department created successfully #'.($department->id ?? null));

            return $department;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $department = Department::findOrFail($id);
            $department->update($data);

            activity()->performedOn($department)->log('Department updated successfully #'.($department->id ?? null));

            return $department;
        });
    }

    public function delete($id)
    {
        $department = Department::find($id);

        if (! $department) {
            return false;
        }

        $department->delete();

        activity()->performedOn($department)->log('Department deleted successfully #'.($department->id ?? null));

        return true;
    }
}
