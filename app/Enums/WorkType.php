<?php

namespace App\Enums;

enum WorkType: string
{
    case So = 'so';
    case Operational = 'operational';

    public function label(): string
    {
        return match ($this) {
            self::So => 'SO',
            self::Operational => 'Operational',
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
