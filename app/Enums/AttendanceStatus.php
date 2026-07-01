<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case EarlyCheckIn = 'early_check_in';
    case Present = 'present';
    case Late = 'late';
    case EarlyLeave = 'early_leave';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Wfh = 'wfh';
    case Visit = 'visit';
    case Holiday = 'holiday';
    case Weekend = 'weekend';

    public function label(): string
    {
        return match ($this) {
            self::EarlyCheckIn => 'Early Check In',
            self::Present => 'Present',
            self::Late => 'Late',
            self::EarlyLeave => 'Early Leave',
            self::Absent => 'Absent',
            self::OnLeave => 'On Leave',
            self::Wfh => 'WFH',
            self::Visit => 'Visit',
            self::Holiday => 'Holiday',
            self::Weekend => 'Weekend',
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => [
            'label' => $status->label(),
            'value' => $status->value,
        ], self::cases());
    }

    /**
     * Single source of truth for "what status does this day get" wherever attendance is
     * classified — the daily report, the CSV-import fallback, and the team overview all defer
     * to this one precedence chain, so a given day can never read differently across screens:
     * Holiday > Cuti (approved leave) > WFH/Visit (approved request) > the day's actual
     * check-in status > Weekend (scheduled rest day) > Alpha (a working day with none of the above).
     */
    public static function classify(
        bool $isHoliday,
        bool $isOnLeave,
        ?string $wfhVisitType,
        ?self $attendanceStatus,
        bool $isRestDay,
    ): self {
        return match (true) {
            $isHoliday => self::Holiday,
            $isOnLeave => self::OnLeave,
            $wfhVisitType === 'wfh' => self::Wfh,
            $wfhVisitType === 'visit' => self::Visit,
            $attendanceStatus !== null => $attendanceStatus,
            $isRestDay => self::Weekend,
            default => self::Absent,
        };
    }
}
