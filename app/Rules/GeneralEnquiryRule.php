<?php

namespace App\Rules;

class GeneralEnquiryRule
{
    public function rules(?int $id = null): array
    {
        return [
            'enquiry_number' => ['nullable', 'string', 'max:100'],
            'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'preferred_destinations' => ['required', 'string'],
            'preferred_travelling_date' => ['required', 'date'],
            'no_of_adults' => ['required', 'integer', 'min:0'],
            'no_of_children' => ['required', 'integer', 'min:0'],
            'requires_mobility_assistance' => ['nullable', 'string'],
        ];
    }
}
