<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Shift;
use Carbon\Carbon;

/**
 * Single source of truth for "is this punch late / does it leave early, and what attendance
 * status does that imply" — shared by a live check-in/check-out punch
 * ({@see \App\Services\AttendanceService}) and an HR-approved attendance correction that
 * retroactively supplies a missed punch time ({@see \App\Services\AttendanceCorrectionService}),
 * so a corrected punch is judged by exactly the same shift-tolerance rules as a real one
 * instead of being waved through as on-time.
 */
class AttendancePunch
{
    /**
     * @return array{status: AttendanceStatus, late_minutes: int}
     */
    public static function checkInStatus(?Shift $shift, Carbon $checkIn): array
    {
        if (! $shift || ! $shift->start_time) {
            return ['status' => AttendanceStatus::Present, 'late_minutes' => 0];
        }

        $start = Carbon::parse($checkIn->toDateString().' '.$shift->start_time);
        $minutesFromStart = intdiv($checkIn->getTimestamp() - $start->getTimestamp(), 60);
        $tolerance = (int) ($shift->late_tolerance_minutes ?? 0);

        $status = match (true) {
            $minutesFromStart < 0 => AttendanceStatus::EarlyCheckIn,
            $minutesFromStart <= $tolerance => AttendanceStatus::Present,
            default => AttendanceStatus::Late,
        };

        return ['status' => $status, 'late_minutes' => max(0, $minutesFromStart)];
    }

    /**
     * @return array{0:int, 1:AttendanceStatus}
     */
    public static function checkOutStatus(?Shift $shift, Attendance $attendance, Carbon $checkOut): array
    {
        $earlyLeave = 0;
        if ($shift && $shift->end_time) {
            $end = Carbon::parse($checkOut->toDateString().' '.$shift->end_time);
            $earlyLeave = max(0, intdiv($end->getTimestamp() - $checkOut->getTimestamp(), 60));
        }

        // Late (set at check-in) stays the headline regardless of checkout. Leaving before the
        // shift ends overrides everything else. Otherwise the check-in's arrival status stands
        // as-is — checking out on time shouldn't flatten an Early Check In back to plain Present.
        $status = match (true) {
            $attendance->status === AttendanceStatus::Late => AttendanceStatus::Late,
            $earlyLeave > 0 => AttendanceStatus::EarlyLeave,
            default => $attendance->status ?? AttendanceStatus::Present,
        };

        return [$earlyLeave, $status];
    }
}
