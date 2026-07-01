<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\HrisScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * The authenticated user's own balances for the current year (quota types only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function myBalances(User $user): array
    {
        $employee = $user->employee;
        if (! $employee) {
            return [];
        }

        return $this->balancesFor($employee->id, (int) Carbon::now()->year);
    }

    /**
     * A specific employee's balances for the given year (defaults to the current year).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forEmployee(int $employeeId, ?int $year = null): array
    {
        return $this->balancesFor($employeeId, $year ?? (int) Carbon::now()->year);
    }

    /**
     * Leave types the employee (or a to-be-created employee of the given gender) can still be
     * allocated: active + balance-tracked + gender-matched + not already allocated (when an
     * employee + year are given).
     *
     * @return array<int, array{value:int, label:string, max_days_per_year:int}>
     */
    public function assignableTypes(?int $employeeId, ?int $year, ?string $gender = null): array
    {
        $query = LeaveType::query()->where('is_active', true)->where('requires_balance', true);

        $employee = $employeeId ? Employee::find($employeeId) : null;
        $gender = $employee?->gender?->value ?? $gender;
        if ($gender) {
            $query->where(fn ($q) => $q->whereNull('gender_restriction')->orWhere('gender_restriction', $gender));
        }
        if ($employee && $year) {
            $taken = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->pluck('leave_type_id');
            $query->whereNotIn('id', $taken);
        }

        return $query->orderBy('name')->get()
            ->map(fn (LeaveType $t) => [
                'value' => $t->id,
                'label' => $t->name,
                'max_days_per_year' => $t->max_days_per_year,
            ])
            ->all();
    }

    /**
     * One row per `leave_balances` record the employee actually holds for the year — i.e. the
     * assigned types. No 0-fill for unallocated types.
     *
     * @return array<int, array<string, mixed>>
     */
    private function balancesFor(int $employeeId, int $year): array
    {
        return LeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->with('leaveType')
            ->get()
            ->sortBy(fn (LeaveBalance $b) => $b->leaveType?->name)
            ->map(fn (LeaveBalance $b) => [
                'id' => $b->id,
                'leave_type_id' => $b->leave_type_id,
                'leave_type' => $b->leaveType?->name,
                'code' => $b->leaveType?->code,
                'year' => $b->year,
                'allocated' => (float) $b->allocated,
                'used' => (float) $b->used,
                'remaining' => (float) $b->allocated - (float) $b->used,
            ])
            ->values()
            ->all();
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

    /** A gender-restricted leave type can only be allocated to a matching employee. */
    private function assertGenderAllowed(array $data): void
    {
        $type = LeaveType::find($data['leave_type_id'] ?? null);
        $employee = Employee::find($data['employee_id'] ?? null);
        if ($type?->gender_restriction && $employee?->gender && $type->gender_restriction !== $employee->gender) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'This leave type is not available for the selected employee.',
            ]);
        }
    }

    public function store(array $data)
    {
        $this->assertGenderAllowed($data);

        return DB::transaction(function () use ($data) {
            $balance = LeaveBalance::create($data);

            activity()->performedOn($balance)->log('Leave balance created successfully #'.($balance->id ?? null));

            return $balance;
        });
    }

    public function update(array $data, $id)
    {
        $this->assertGenderAllowed($data);

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
