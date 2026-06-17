<?php

namespace App\Enums;

enum PositionLevel: string
{
    case Staff = 'staff';
    case Supervisor = 'supervisor';
    case Manager = 'manager';
    case Director = 'director';
    case Ceo = 'ceo';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Supervisor => 'Supervisor',
            self::Manager => 'Manager',
            self::Director => 'Director',
            self::Ceo => 'CEO',
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
        return array_map(fn (self $level) => [
            'label' => $level->label(),
            'value' => $level->value,
        ], self::cases());
    }
}
