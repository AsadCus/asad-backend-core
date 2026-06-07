<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class PackageProposalRule
{
    public function rules(?int $id = null): array
    {
        return [
            'proposal_number' => ['nullable', 'string', 'max:100', Rule::unique('package_proposals', 'proposal_number')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],

            'departure_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'total_seats' => ['required', 'integer', 'min:1'],

            'price_single' => ['nullable', 'numeric', 'min:0'],
            'price_double' => ['nullable', 'numeric', 'min:0'],
            'price_triple' => ['nullable', 'numeric', 'min:0'],
            'price_quad' => ['nullable', 'numeric', 'min:0'],
            'child_with_bed_price' => ['nullable', 'numeric', 'min:0'],
            'child_no_bed_price' => ['nullable', 'numeric', 'min:0'],
            'infant_price' => ['nullable', 'numeric', 'min:0'],

            'expenditure' => ['nullable', 'array'],
            'expenditure.*.title' => ['nullable', 'string', 'max:255'],
            'expenditure.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'expenditure.*.items' => ['nullable', 'array'],
            'expenditure.*.items.*.item_name' => ['nullable', 'string', 'max:255'],
            'expenditure.*.items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'expenditure.*.items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'expenditure.*.items.*.remarks' => ['nullable', 'string'],
            'expenditure.*.items.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'expenditure.*.extensions' => ['nullable', 'array'],
            'expenditure.*.extensions.*.name' => ['nullable', 'string', 'max:255'],
            'expenditure.*.extensions.*.calculation_mode' => ['nullable', 'in:fixed,percentage'],
            'expenditure.*.extensions.*.calculation_value' => ['nullable', 'numeric'],
            'expenditure.*.extensions.*.sort_order' => ['nullable', 'integer', 'min:1'],

            'passenger_simulation' => ['nullable', 'array'],
            'passenger_simulation.single' => ['nullable', 'integer', 'min:0'],
            'passenger_simulation.double' => ['nullable', 'integer', 'min:0'],
            'passenger_simulation.triple' => ['nullable', 'integer', 'min:0'],
            'passenger_simulation.quad' => ['nullable', 'integer', 'min:0'],
            'passenger_simulation.child_with_bed' => ['nullable', 'integer', 'min:0'],
            'passenger_simulation.child_no_bed' => ['nullable', 'integer', 'min:0'],
            'passenger_simulation.infant' => ['nullable', 'integer', 'min:0'],

            'officials' => ['nullable', 'array'],
            'officials.*.type' => ['nullable', 'string', 'max:255'],
            'officials.*.name' => ['nullable', 'string', 'max:255'],

            'remarks' => ['nullable', 'string'],
        ];
    }

    public function submitRules(): array
    {
        return [
            'approver_user_ids' => ['required', 'array', 'min:1'],
            'approver_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function rejectRules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
