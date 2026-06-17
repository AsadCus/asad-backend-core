<?php

namespace App\Enums;

enum HolidayType: string
{
    case National = 'national';
    case Religious = 'religious';
    case Company = 'company';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::National => 'National',
            self::Religious => 'Religious',
            self::Company => 'Company',
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
