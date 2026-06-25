<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\OrgUnitType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OrgUnit;
use App\Models\User;
use App\Models\WorkScheduleDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TeamOverviewService
{
    /**
     * @return array<string, mixed>
     */
    public function getOverview(User $user, string $period): array
    {
        $me = $user->employee;
        abort_unless($me, 422);

        $today = Carbon::today();
        [$start, $end] = $this->resolveRange($period, $today);

        $subordinates = Employee::query()
            ->where('supervisor_id', $me->id)
            // The org tree is shallow — eager-load the whole ancestor chain once so
            // nearestOfType()/resolveWorkLocation() below never trigger extra queries.
            ->with(['user', 'orgUnit.parent.parent.parent.parent', 'workLocation.parent.parent.parent.parent'])
            ->get();

        $employeeIds = $subordinates->pluck('id');

        $attendancesByEmployee = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('shift')
            ->get()
            ->groupBy('employee_id');

        $schedulesByEmployee = EmployeeSchedule::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('effective_from', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start->toDateString()))
            ->with('workSchedule.workScheduleDays.shift')
            ->orderBy('effective_from')
            ->get()
            ->groupBy('employee_id');

        $leavesByEmployee = LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', [ApprovalStatus::PendingSupervisor->value, ApprovalStatus::PendingHr->value, ApprovalStatus::Approved->value])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->with('leaveType')
            ->get()
            ->groupBy('employee_id');

        $holidayDates = $this->holidayDatesInRange($start, $end);

        $summary = ['total' => $subordinates->count(), 'present' => 0, 'late' => 0, 'on_leave' => 0, 'absent' => 0];
        $details = ['present' => [], 'late' => [], 'on_leave' => [], 'absent' => []];
        $seenLeaveRequestIds = [];
        $members = [];

        foreach ($subordinates as $emp) {
            $businessUnit = $emp->orgUnit?->nearestOfType(OrgUnitType::BusinessUnit);
            $branch = $emp->orgUnit?->nearestOfType(OrgUnitType::Branch);
            $department = $emp->orgUnit?->nearestOfType(OrgUnitType::Department);
            $division = $emp->orgUnit?->nearestOfType(OrgUnitType::Division);

            $attendancesByDate = ($attendancesByEmployee->get($emp->id) ?? collect())
                ->keyBy(fn (Attendance $a) => $a->date->toDateString());
            $empSchedules = $schedulesByEmployee->get($emp->id) ?? collect();
            $empLeaves = $leavesByEmployee->get($emp->id) ?? collect();

            $counts = ['present' => 0, 'late' => 0, 'on_leave' => 0, 'absent' => 0];
            $todayRow = null;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateStr = $date->toDateString();
                $day = $this->scheduleDayFor($empSchedules, $date);
                $att = $attendancesByDate->get($dateStr);
                $isHoliday = isset($holidayDates[$dateStr]);
                $isWorkday = $day && $day->is_workday;

                if (! $isHoliday && $isWorkday) {
                    if ($att) {
                        $isPresentLike = in_array($att->status, [
                            AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::EarlyLeave,
                        ], true);
                        $isLate = $att->status === AttendanceStatus::Late;

                        if ($isPresentLike) {
                            $counts['present']++;
                            $details['present'][] = $this->mapInstanceRow(
                                $emp, $businessUnit, $branch, $department, $division,
                                $date, $att->shift?->name ?? $day->shift?->name, $att->check_in_at?->format('H:i'),
                            );
                        }
                        if ($isLate) {
                            $counts['late']++;
                            $details['late'][] = $this->mapInstanceRow(
                                $emp, $businessUnit, $branch, $department, $division,
                                $date, $att->shift?->name ?? $day->shift?->name, $att->check_in_at?->format('H:i'),
                            );
                        }
                    } else {
                        $leave = $empLeaves->first(fn (LeaveRequest $l) => $l->start_date->lte($date) && $l->end_date->gte($date));
                        if ($leave) {
                            $counts['on_leave']++;
                            if (! isset($seenLeaveRequestIds[$leave->id])) {
                                $seenLeaveRequestIds[$leave->id] = true;
                                $details['on_leave'][] = $this->mapLeaveRow($emp, $businessUnit, $branch, $department, $division, $leave);
                            }
                        } else {
                            $counts['absent']++;
                            $details['absent'][] = $this->mapInstanceRow(
                                $emp, $businessUnit, $branch, $department, $division, $date, null, null,
                            );
                        }
                    }
                }

                $isSingleDayPeriod = in_array($period, ['today', 'yesterday'], true);
                if ($isSingleDayPeriod && $date->isSameDay($start)) {
                    $statusLabel = $att?->status?->label() ?? 'Absent';
                    $scheduledShiftName = $isWorkday ? $day->shift?->name : null;
                    $todayRow = [
                        'id' => $emp->id,
                        'name' => $emp->user?->name ?? '—',
                        'employee_no' => $emp->employee_no,
                        'nik' => $emp->nik,
                        'avatar_url' => $emp->user?->avatar_url,
                        'org_unit' => $emp->orgUnit?->name,
                        'business_unit' => $businessUnit?->name,
                        'business_unit_id' => $businessUnit?->id,
                        'branch' => $branch?->name,
                        'branch_id' => $branch?->id,
                        'department' => $department?->name,
                        'department_id' => $department?->id,
                        'division' => $division?->name,
                        'division_id' => $division?->id,
                        'work_location' => $emp->resolveWorkLocation()?->name,
                        'status' => $statusLabel,
                        'time_in' => $att?->check_in_at?->format('H:i'),
                        'time_out' => $att?->check_out_at?->format('H:i'),
                        'shift' => $att?->shift?->name ?? $scheduledShiftName,
                        'attendance_id' => $att?->id,
                    ];
                }
            }

            $summary['present'] += $counts['present'];
            $summary['late'] += $counts['late'];
            $summary['on_leave'] += $counts['on_leave'];
            $summary['absent'] += $counts['absent'];

            $isSingleDayPeriod = in_array($period, ['today', 'yesterday'], true);
            if ($isSingleDayPeriod) {
                $members[] = $todayRow;
            } else {
                $members[] = [
                    'id' => $emp->id,
                    'name' => $emp->user?->name ?? '—',
                    'employee_no' => $emp->employee_no,
                    'nik' => $emp->nik,
                    'avatar_url' => $emp->user?->avatar_url,
                    'business_unit' => $businessUnit?->name,
                    'branch' => $branch?->name,
                    'department' => $department?->name,
                    'division' => $division?->name,
                    'work_location' => $emp->resolveWorkLocation()?->name,
                    'present_count' => $counts['present'],
                    'late_count' => $counts['late'],
                    'on_leave_count' => $counts['on_leave'],
                    'absent_count' => $counts['absent'],
                ];
            }
        }

        usort($members, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return [
            'period' => $period,
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'summary' => $summary,
            'details' => $details,
            'members' => $members,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(string $period, Carbon $today): array
    {
        // Never project into the future — an "end" past today is capped back to today.
        // Compared by date-only string since endOfWeek()/endOfMonth() carry a 23:59:59 time.
        $cap = fn (Carbon $end) => $end->toDateString() > $today->toDateString() ? $today->copy() : $end;

        return match ($period) {
            'yesterday' => [$today->copy()->yesterday(), $today->copy()->yesterday()],
            'week' => [$today->copy()->startOfWeek(), $cap($today->copy()->endOfWeek())],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'month' => [$today->copy()->startOfMonth(), $cap($today->copy()->endOfMonth())],
            'last_month' => [$today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $cap($today->copy()->endOfYear())],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            default => [$today->copy(), $today->copy()],
        };
    }

    /**
     * The matching work_schedule_days row for $date among the employee's (small) set of
     * schedule rows overlapping the queried range — picks the one with the latest
     * effective_from that's still active on $date, mirroring the single-day lookup in
     * AttendanceService::resolveScheduleDay() but reusable across every date in the range.
     *
     * @param  Collection<int, EmployeeSchedule>  $schedules
     */
    private function scheduleDayFor(Collection $schedules, Carbon $date): ?WorkScheduleDay
    {
        $active = $schedules
            ->filter(fn (EmployeeSchedule $s) => $s->effective_from->lte($date)
                && ($s->effective_to === null || $s->effective_to->gte($date)))
            ->sortByDesc('effective_from')
            ->first();

        if (! $active || ! $active->workSchedule) {
            return null;
        }

        return $active->workSchedule->workScheduleDays->firstWhere('day_of_week', $date->dayOfWeek);
    }

    /**
     * Every holiday date within the range as a `['Y-m-d' => true]` set — prefetched once so the
     * per-employee/per-day walk below never re-queries (unlike calling Holiday::isHoliday() in a loop).
     *
     * @return array<string, bool>
     */
    private function holidayDatesInRange(Carbon $start, Carbon $end): array
    {
        $set = [];

        Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->each(function ($date) use (&$set) {
                $set[$date->toDateString()] = true;
            });

        $recurring = Holiday::query()->where('is_recurring', true)->get(['date']);
        if ($recurring->isNotEmpty()) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                foreach ($recurring as $holiday) {
                    if ($holiday->date->month === $date->month && $holiday->date->day === $date->day) {
                        $set[$date->toDateString()] = true;
                    }
                }
            }
        }

        return $set;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInstanceRow(
        Employee $emp,
        ?OrgUnit $businessUnit,
        ?OrgUnit $branch,
        ?OrgUnit $department,
        ?OrgUnit $division,
        Carbon $date,
        ?string $shift,
        ?string $timeIn,
    ): array {
        return [
            'employee_id' => $emp->id,
            'name' => $emp->user?->name ?? '—',
            'business_unit' => $businessUnit?->name,
            'branch' => $branch?->name,
            'department' => $department?->name,
            'division' => $division?->name,
            'date' => $date->toDateString(),
            'shift' => $shift,
            'time_in' => $timeIn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLeaveRow(
        Employee $emp,
        ?OrgUnit $businessUnit,
        ?OrgUnit $branch,
        ?OrgUnit $department,
        ?OrgUnit $division,
        LeaveRequest $leave,
    ): array {
        return [
            'employee_id' => $emp->id,
            'name' => $emp->user?->name ?? '—',
            'business_unit' => $businessUnit?->name,
            'branch' => $branch?->name,
            'department' => $department?->name,
            'division' => $division?->name,
            'leave_type' => $leave->leaveType?->name,
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'reason' => $leave->reason,
            'status' => $leave->status?->label(),
        ];
    }
}
