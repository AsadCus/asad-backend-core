<?php

namespace App\Enums;

enum BusinessTripReportStatus: string
{
    case PendingLeader = 'pending_leader';
    case PendingFinance = 'pending_finance';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingLeader => 'Pending Leader',
            self::PendingFinance => 'Pending Finance',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
