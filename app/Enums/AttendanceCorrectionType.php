<?php

namespace App\Enums;

enum AttendanceCorrectionType: string
{
    case MissedCheckIn = 'missed_check_in';
    case MissedCheckOut = 'missed_check_out';
    case Wfh = 'wfh';
    case Visit = 'visit';
    case LocationIssue = 'location_issue';
    case Sick = 'sick';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MissedCheckIn => 'Missed Check-in',
            self::MissedCheckOut => 'Missed Check-out',
            self::Wfh => 'Work From Home',
            self::Visit => 'Visit',
            self::LocationIssue => 'Location Issue',
            self::Sick => 'Sick',
            self::Other => 'Other',
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
        return array_map(fn (self $type) => [
            'label' => $type->label(),
            'value' => $type->value,
        ], self::cases());
    }
}
