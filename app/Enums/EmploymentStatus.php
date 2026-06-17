<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Probation = 'probation';
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Intern = 'intern';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Probation => 'Probation',
            self::Permanent => 'Permanent',
            self::Contract => 'Contract',
            self::Intern => 'Intern',
            self::Terminated => 'Terminated',
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
