<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Revised = 'revised';
    case Ready = 'ready';
    case Accepted = 'accepted';
    case Converted = 'converted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Revised => 'Revised',
            self::Ready => 'Ready',
            self::Accepted => 'Accepted',
            self::Converted => 'Converted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
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
