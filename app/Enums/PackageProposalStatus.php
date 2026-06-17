<?php

namespace App\Enums;

enum PackageProposalStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::PendingApproval,
            self::PendingApproval => in_array($target, [self::Approved, self::Rejected]),
            self::Approved => false,
            self::Rejected => $target === self::Draft,
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
