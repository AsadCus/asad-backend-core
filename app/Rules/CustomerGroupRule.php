<?php

namespace App\Rules;

class CustomerGroupRule
{
    public function rules(?bool $requireEnquiry = true): array
    {
        $rules = [
            'members' => ['required', 'array', 'min:1'],
            'members.*.full_name' => ['required', 'string', 'max:255'],
            'members.*.email' => ['required', 'email', 'max:255'],
            'members.*.contact_number' => ['required', 'string', 'max:30'],
            'members.*.nric_number' => ['nullable', 'string', 'max:50'],
            'members.*.address' => ['nullable', 'string', 'max:500'],
            'members.*.is_leader' => ['required', 'boolean'],
        ];

        if ($requireEnquiry) {
            $rules['enquiry_id'] = ['required', 'integer', 'exists:enquiries,id'];
        }

        return $rules;
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'members.required' => 'At least one member is required.',
            'members.min' => 'At least one member is required.',
        ];
    }
}
