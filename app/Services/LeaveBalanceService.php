<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Support\HrisScope;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    public function getForDataTable()
    {
        return HrisScope::applyViaEmployee(
            LeaveBalance::query()->with(['employee.user', 'leaveType'])
        )
            ->orderByDesc('year')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'employee_id' => $q->employee_id,
                'employee_name' => $q->employee?->user?->name ?? $q->employee?->employee_no,
                'leave_type_id' => $q->leave_type_id,
                'leave_type_name' => $q->leaveType?->name,
                'year' => $q->year,
                'allocated' => (float) $q->allocated,
                'used' => (float) $q->used,
                'remaining' => (float) $q->allocated - (float) $q->used,
                'note' => $q->note,
            ]);
    }

    public function getForEditShow($id)
    {
        $balance = LeaveBalance::findOrFail($id);

        return [
            'id' => $balance->id,
            'employee_id' => $balance->employee_id,
            'leave_type_id' => $balance->leave_type_id,
            'year' => $balance->year,
            'allocated' => (float) $balance->allocated,
            'used' => (float) $balance->used,
            'note' => $balance->note,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $balance = LeaveBalance::create($data);

            activity()->performedOn($balance)->log('Leave balance created successfully #'.($balance->id ?? null));

            return $balance;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $balance = LeaveBalance::findOrFail($id);
            $balance->update($data);

            activity()->performedOn($balance)->log('Leave balance updated successfully #'.($balance->id ?? null));

            return $balance;
        });
    }

    public function delete($id)
    {
        $balance = LeaveBalance::find($id);

        if (! $balance) {
            return false;
        }

        $balance->delete();

        activity()->performedOn($balance)->log('Leave balance deleted successfully #'.($balance->id ?? null));

        return true;
    }
}
