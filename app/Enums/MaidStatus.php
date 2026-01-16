<?php

namespace App\Enums;

enum MaidStatus: string
{
    case AVAILABLE = 'available';
    case INTERVIEWING = 'interviewing';
    case PENDING = 'pending';
    case ASSIGNED = 'assigned';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::INTERVIEWING => 'Interviewing',
            self::PENDING => 'Pending',
            self::ASSIGNED => 'Assigned',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available for placement',
            self::INTERVIEWING => 'Undergoing interview process',
            self::PENDING => 'Customer interested, awaiting final confirmation (grace period 3 days)',
            self::ASSIGNED => 'Assigned to customer - final status',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::AVAILABLE => in_array($newStatus, [self::INTERVIEWING, self::ASSIGNED]), // Allow direct transition to ASSIGNED
            self::INTERVIEWING => in_array($newStatus, [self::AVAILABLE, self::PENDING]),
            self::PENDING => in_array($newStatus, [self::ASSIGNED, self::AVAILABLE]),
            self::ASSIGNED => $newStatus === self::AVAILABLE, // Can revert to AVAILABLE if needed
        };
    }
}

