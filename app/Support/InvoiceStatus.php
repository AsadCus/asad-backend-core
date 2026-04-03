<?php

namespace App\Support;

final class InvoiceStatus
{
    public const Draft = 'draft';

    public const Issued = 'issued';

    public const Paid = 'paid';

    public const Overdue = 'overdue';

    public const Cancelled = 'cancelled';

    public const Refund = 'refund';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::Draft,
            self::Issued,
            self::Paid,
            self::Overdue,
            self::Cancelled,
            self::Refund,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function userSelectableValues(): array
    {
        return [
            self::Draft,
            self::Issued,
            self::Paid,
            self::Overdue,
            self::Cancelled,
        ];
    }

    public static function isRefund(?string $status): bool
    {
        return strtolower(trim((string) $status)) === self::Refund;
    }
}
