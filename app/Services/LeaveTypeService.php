<?php

namespace App\Services;

use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;

class LeaveTypeService
{
    public function getForDataTable()
    {
        return LeaveType::query()->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'max_days_per_year' => $q->max_days_per_year,
            'requires_balance' => (bool) $q->requires_balance,
            'requires_attachment' => (bool) $q->requires_attachment,
            'is_paid' => (bool) $q->is_paid,
            'gender_restriction' => $q->gender_restriction?->value,
            'description' => $q->description,
            'is_active' => (bool) $q->is_active,
        ]);
    }

    public function getForFilter()
    {
        return LeaveType::query()->orderBy('name')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
            'code' => $q->code,
        ]);
    }

    public function getForEditShow($id)
    {
        $leaveType = LeaveType::findOrFail($id);

        return [
            'id' => $leaveType->id,
            'name' => $leaveType->name,
            'code' => $leaveType->code,
            'max_days_per_year' => $leaveType->max_days_per_year,
            'requires_balance' => (bool) $leaveType->requires_balance,
            'requires_attachment' => (bool) $leaveType->requires_attachment,
            'is_paid' => (bool) $leaveType->is_paid,
            'gender_restriction' => $leaveType->gender_restriction?->value,
            'description' => $leaveType->description,
            'is_active' => (bool) $leaveType->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $leaveType = LeaveType::create($data);

            activity()->performedOn($leaveType)->log('Leave type created successfully #'.($leaveType->id ?? null));

            return $leaveType;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $leaveType = LeaveType::findOrFail($id);
            $leaveType->update($data);

            activity()->performedOn($leaveType)->log('Leave type updated successfully #'.($leaveType->id ?? null));

            return $leaveType;
        });
    }

    public function delete($id)
    {
        $leaveType = LeaveType::find($id);

        if (! $leaveType) {
            return false;
        }

        $leaveType->delete();

        activity()->performedOn($leaveType)->log('Leave type deleted successfully #'.($leaveType->id ?? null));

        return true;
    }
}
