<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\Employee;
use App\Models\WfhVisitRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "is there an approved WFH/Visit covering this employee on this
 * date" — used wherever attendance logic needs to recognize a remote-work day, whether that's
 * one employee on one date (geofence bypass, today's status) or many employees across a date
 * range (the daily report, the team overview). Centralizing this avoids the WFH/Visit query
 * and day-expansion logic drifting between AttendanceService and TeamOverviewService.
 */
class WfhVisitLookupService
{
    /** The employee's fully-approved WFH/Visit request covering $date, if any. */
    public function approvedForDate(Employee $employee, Carbon $date): ?WfhVisitRequest
    {
        return WfhVisitRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', ApprovalStatus::Approved)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
    }

    /**
     * Approved WFH/Visit type ('wfh'|'visit') for every employee/day in [from, to] that an
     * approved request covers — one batched query instead of one approvedForDate() call per
     * employee per day.
     *
     * @param  array<int>  $employeeIds
     * @return Collection<string, string> "employeeId:Y-m-d" => 'wfh'|'visit'
     */
    public function approvedTypeByDayKey(array $employeeIds, Carbon $from, Carbon $to): Collection
    {
        if (empty($employeeIds)) {
            return collect();
        }

        $requests = WfhVisitRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', ApprovalStatus::Approved)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get(['employee_id', 'type', 'start_date', 'end_date']);

        $byDayKey = collect();
        foreach ($requests as $request) {
            $rangeStart = $request->start_date->greaterThan($from) ? $request->start_date->copy() : $from->copy();
            $rangeEnd = $request->end_date->lessThan($to) ? $request->end_date->copy() : $to->copy();

            foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                $byDayKey->put(self::dayKey($request->employee_id, $date), $request->type);
            }
        }

        return $byDayKey;
    }

    public static function dayKey(int $employeeId, Carbon|string $date): string
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return "{$employeeId}:{$date}";
    }
}
