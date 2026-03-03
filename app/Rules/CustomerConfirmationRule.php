<?php

namespace App\Rules;

class CustomerConfirmationRule
{
    public function rules(?bool $requireEnquiry = true): array
    {
        $rules = [
            // Group-level fields
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'package_room_type' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'package_category' => ['nullable', 'string', 'in:classic_umrah,deluxe_umrah'],
            'date_of_application' => ['required', 'date'],

            // Members
            'members' => ['required', 'array', 'min:1'],
            'members.*.member_id' => ['nullable', 'integer', 'exists:customer_confirmation_members,id'],
            'members.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'members.*.name' => ['required', 'string', 'max:255'],
            'members.*.email' => ['required', 'email', 'max:255'],
            'members.*.contact_number' => ['required', 'string', 'max:30'],
            'members.*.nric_number' => ['required', 'string', 'max:50'],
            'members.*.address' => ['required', 'string', 'max:500'],
            'members.*.is_leader' => ['required', 'boolean'],
            'members.*.sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'members.*.role' => ['nullable', 'string', 'max:255'],

            // Biodata per member
            'members.*.first_time_umrah' => ['nullable', 'boolean'],
            'members.*.nationality' => ['required', 'string', 'max:100'],
            'members.*.passport_number' => ['required', 'string', 'max:50'],
            'members.*.passport_issue_date' => ['required', 'date'],
            'members.*.passport_expiry_date' => ['required', 'date'],
            'members.*.passport_place_of_issue' => ['required', 'string', 'max:255'],
            'members.*.gender' => ['required', 'string', 'in:male,female'],
            'members.*.marital_status' => ['required', 'string', 'in:single,married,divorced,widowed'],
            'members.*.date_of_birth' => ['required', 'date'],
            'members.*.place_of_birth' => ['required', 'string', 'max:255'],
            'members.*.has_chronic_disease' => ['nullable', 'boolean'],
            'members.*.chronic_disease_details' => ['nullable', 'string', 'max:1000'],

            // Image uploads per member
            'members.*.passport_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'members.*.photo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
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
            'date_of_application.required' => 'The date of application is required.',
        ];
    }
}
