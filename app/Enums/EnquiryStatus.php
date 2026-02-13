<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    case NewLead = 'new_lead';
    case Contacted = 'contacted';
    case Negotiating = 'negotiating';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'New Lead',
            self::Contacted => 'Contacted',
            self::Negotiating => 'Negotiating',
            self::Confirmed => 'Confirmed',
        };
    }

    /**
     * Get the next allowed status in the workflow.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::NewLead => self::Contacted,
            self::Contacted => self::Negotiating,
            self::Negotiating => self::Confirmed,
            self::Confirmed => null,
        };
    }

    /**
     * Check if the transition to the given status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return $this->next() === $target;
    }

    /**
     * Get all status values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all statuses as options for frontend select.
     *
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
