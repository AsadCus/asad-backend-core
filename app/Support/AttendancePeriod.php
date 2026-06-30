<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Resolves the {from, to} date range a "month" actually covers for attendance/payroll
 * purposes — not every company runs a plain calendar month. A cutoff day greater than 1
 * means the period labeled e.g. "June 2026" runs from that day in May through the day
 * before it in June (the common "16th to 15th" payroll cycle some companies use because
 * payday falls around the 25th). See {@see \App\Models\OrgUnit::resolveAttendanceCutoffDay()}
 * for where the cutoff day itself comes from.
 */
class AttendancePeriod
{
    public const MIN_CUTOFF_DAY = 1;

    public const MAX_CUTOFF_DAY = 28;

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public static function forMonth(int $year, int $month, int $cutoffDay): array
    {
        if ($cutoffDay < self::MIN_CUTOFF_DAY || $cutoffDay > self::MAX_CUTOFF_DAY) {
            throw new InvalidArgumentException(sprintf(
                'Attendance cutoff day must be between %d and %d, got %d.',
                self::MIN_CUTOFF_DAY, self::MAX_CUTOFF_DAY, $cutoffDay,
            ));
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();

        // Day 1 is just a plain calendar month — the common case, and what every org unit
        // without an explicit cutoff configured falls back to.
        if ($cutoffDay === self::MIN_CUTOFF_DAY) {
            return ['from' => $monthStart->copy(), 'to' => $monthStart->copy()->endOfMonth()];
        }

        return [
            'from' => $monthStart->copy()->subMonthNoOverflow()->day($cutoffDay),
            'to' => $monthStart->copy()->day($cutoffDay)->subDay(),
        ];
    }
}
