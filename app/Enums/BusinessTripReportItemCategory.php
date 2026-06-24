<?php

namespace App\Enums;

enum BusinessTripReportItemCategory: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Settlement = 'settlement';
    case Ticket = 'ticket';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Settlement => 'Settlement',
            self::Ticket => 'Ticket',
        };
    }

    /**
     * Categories an uploaded receipt actually proves (used for the report's receipt-backed %).
     *
     * @return array<int, self>
     */
    public static function receiptable(): array
    {
        return [self::Expense, self::Ticket];
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
        return array_map(fn (self $category) => [
            'label' => $category->label(),
            'value' => $category->value,
        ], self::cases());
    }
}
