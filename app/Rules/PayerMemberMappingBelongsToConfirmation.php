<?php

namespace App\Rules;

use App\Models\CustomerConfirmation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PayerMemberMappingBelongsToConfirmation implements ValidationRule
{
    public function __construct(
        private readonly int $confirmationId,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || empty($value)) {
            return;
        }

        $confirmation = CustomerConfirmation::with('members.customer.user')
            ->find($this->confirmationId);

        if (! $confirmation) {
            $fail('The selected customer confirmation is invalid.');

            return;
        }

        $payerMemberIds = collect(array_keys($value))
            ->map(fn ($memberId) => (int) $memberId)
            ->values();

        $coveredMemberIds = collect($value)
            ->flatten()
            ->map(fn ($memberId) => (int) $memberId)
            ->unique()
            ->values();

        $groupMemberIds = $confirmation->members
            ->pluck('id')
            ->map(fn ($memberId) => (int) $memberId);

        $invalidMemberIds = $coveredMemberIds
            ->merge($payerMemberIds)
            ->unique()
            ->diff($groupMemberIds)
            ->values();

        if ($invalidMemberIds->isNotEmpty()) {
            $fail('Cannot generate quotations: payer/member mapping contains member(s) outside this confirmation.');

            return;
        }

        if (! $confirmation->package_id) {
            $fail('Cannot generate quotations: no package has been selected for this confirmation.');

            return;
        }

        $membersWithoutPlan = $confirmation->members
            ->whereIn('id', $coveredMemberIds->all())
            ->filter(fn ($member) => empty($member->sharing_plan));

        if ($membersWithoutPlan->isNotEmpty()) {
            $names = $membersWithoutPlan
                ->map(fn ($member) => $member->customer?->user?->name ?? "Member #{$member->id}")
                ->implode(', ');

            $fail("Cannot generate quotations: the following members do not have a sharing plan assigned: {$names}.");

            return;
        }

        $cancelledMembers = $confirmation->members
            ->whereIn('id', $coveredMemberIds->all())
            ->filter(fn ($member) => $member->status === 'cancelled');

        if ($cancelledMembers->isNotEmpty()) {
            $names = $cancelledMembers
                ->map(fn ($member) => $member->customer?->user?->name ?? "Member #{$member->id}")
                ->implode(', ');

            $fail("Cannot generate quotations: cancelled member(s) cannot be included: {$names}.");
        }
    }
}
