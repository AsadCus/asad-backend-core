<?php

namespace App\Rules;

use App\Enums\BusinessTripReportItemCategory;
use App\Enums\WorkType;
use Illuminate\Validation\Rule;

class BusinessTripRule
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function storeRules(): array
    {
        return [
            'work_type' => ['required', Rule::in(WorkType::values())],
            'so_reference' => ['required_if:work_type,so', 'nullable', 'string', 'max:100'],
            'project_name' => ['required', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'destination_address' => ['required', 'string', 'max:1000'],
            'depart_at' => ['required', 'date'],
            'return_at' => ['required', 'date', 'after_or_equal:depart_at'],
            'hotel_ref' => ['nullable', 'string', 'max:255'],
            'origin_terminal' => ['nullable', 'string', 'max:255'],
            'dest_terminal' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'bank' => ['required', 'string', 'max:100'],
            'account_no' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:255'],
            'cost_breakdown' => ['required', 'array', 'min:1'],
            'cost_breakdown.*.title' => ['nullable', 'string', 'max:255'],
            'cost_breakdown.*.items' => ['required', 'array', 'min:1'],
            'cost_breakdown.*.items.*.description' => ['required', 'string', 'max:255'],
            'cost_breakdown.*.items.*.cost' => ['required', 'numeric', 'min:0'],
            'cost_breakdown.*.items.*.qty' => ['required', 'numeric', 'min:0'],
            'cost_breakdown.*.items.*.unit' => ['nullable', 'string', 'max:50'],
            'members' => ['nullable', 'array'],
            'members.*.name' => ['required', 'string', 'max:255'],
            'members.*.jabatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function decisionRules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Multi-ledger report submission — one row per income / expense / settlement / ticket entry.
     *
     * @return array<string, array<int, mixed>>
     */
    public function reportRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', Rule::in(BusinessTripReportItemCategory::values())],
            'items.*.date' => ['required', 'date'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.kategori' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            // Echoes back an existing item's stored path when the row is resubmitted (e.g. after
            // a rejection) without picking a new file — so a previously uploaded receipt isn't
            // silently dropped just because the line items are replaced wholesale.
            'items.*.attachment_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
