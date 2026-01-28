<?php

namespace App\Rules;

class GeneralEnquiryRule
{
    public function rules(?int $id = null): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'preferred_destinations' => ['required', 'string'],
            'preferred_travelling_date' => ['required', 'date'],
            'no_of_adults' => ['required', 'integer', 'min:0'],
            'no_of_children' => ['required', 'integer', 'min:0'],
            'requires_mobility_assistance' => ['nullable', 'string'],
        ];
    }
}
