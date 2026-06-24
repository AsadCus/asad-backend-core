<?php

namespace App\Enums;

enum BusinessTripStatus: string
{
    case PendingLeader = 'pending_leader';
    case PendingHc = 'pending_hc';
    case PendingFinance = 'pending_finance';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingLeader => 'Pending Leader',
            self::PendingHc => 'Pending HC',
            self::PendingFinance => 'Pending Finance',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
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
}
