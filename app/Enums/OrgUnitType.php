<?php

namespace App\Enums;

enum OrgUnitType: string
{
    case Holding = 'holding';
    case BusinessUnit = 'business_unit';
    case Branch = 'branch';
    case Department = 'department';
    case Division = 'division';

    public function label(): string
    {
        return match ($this) {
            self::Holding => 'Holding',
            self::BusinessUnit => 'Business Unit',
            self::Branch => 'Branch',
            self::Department => 'Department',
            self::Division => 'Division',
        };
    }

    /**
     * Org-unit types allowed as the parent of a node of this type.
     * Empty array = must be a root (no parent) — i.e. Holding.
     *
     * @return array<int, self>
     */
    public function allowedParents(): array
    {
        return match ($this) {
            self::Holding => [],
            self::BusinessUnit => [self::Holding],
            self::Branch => [self::BusinessUnit],
            self::Department => [self::Holding, self::BusinessUnit, self::Branch],
            self::Division => [self::Department],
        };
    }

    /**
     * Whether this type carries geofence/location attributes (lat/long/radius).
     */
    public function isLocation(): bool
    {
        return $this === self::Branch;
    }

    /**
     * Validate that $parent is an allowed parent for a node of this type.
     */
    public function canHaveParent(?self $parent): bool
    {
        $allowed = $this->allowedParents();

        if ($parent === null) {
            return $allowed === [];
        }

        return in_array($parent, $allowed, true);
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
