<?php

namespace App\Rules;

class CustomerConfirmationRule
{
    public function rules(?bool $requireEnquiry = true): array
    {
        $rules = [
            // Group-level fields
            'number' => ['nullable', 'string', 'max:100'],
            'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
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
            'members.*.nric_number' => ['nullable', 'string', 'max:50'],
            'members.*.address' => ['nullable', 'string', 'max:500'],
            'members.*.is_leader' => ['required', 'boolean'],
            'members.*.status' => ['nullable', 'string', 'in:pending_payment,partially_paid,fully_paid,overpaid,cancelled'],
            'members.*.sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad,child_with_bed,child_no_bed,infant'],
            'members.*.relationship' => ['nullable', 'string', 'max:255'],
            'members.*.role' => ['nullable', 'string', 'max:255'],

            // Biodata per member
            'members.*.first_time_umrah' => ['nullable', 'boolean'],
            'members.*.nationality' => ['nullable', 'string', 'max:100'],
            'members.*.passport_number' => ['nullable', 'string', 'max:50'],
            'members.*.passport_issue_date' => ['nullable', 'date'],
            'members.*.passport_expiry_date' => ['nullable', 'date'],
            'members.*.passport_place_of_issue' => ['nullable', 'string', 'max:255'],
            'members.*.gender' => ['nullable', 'string', 'in:male,female'],
            'members.*.marital_status' => ['nullable', 'string', 'in:single,married,divorced,widowed'],
            'members.*.date_of_birth' => ['nullable', 'date'],
            'members.*.place_of_birth' => ['nullable', 'string', 'max:255'],
            'members.*.has_chronic_disease' => ['nullable', 'boolean'],
            'members.*.is_using_wheelchair' => ['nullable', 'boolean'],
            'members.*.chronic_disease_details' => ['nullable', 'string', 'max:1000'],

            // Image uploads per member
            'members.*.passport_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'members.*.photo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'members.*.passport_file_name' => ['nullable', 'string', 'max:255'],
            'members.*.photo_file_name' => ['nullable', 'string', 'max:255'],
            'members.*.passport_file_removed' => ['nullable', 'boolean'],
            'members.*.photo_file_removed' => ['nullable', 'boolean'],
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
            'members.*.passport_file.mimes' => 'Passport attachment must be JPG, JPEG, PNG, or PDF.',
            'members.*.passport_file.max' => 'Passport attachment file must not be more than 5000KB (5MB).',
            'members.*.photo_file.mimes' => 'Photo attachment must be JPG, JPEG, or PNG.',
            'members.*.photo_file.max' => 'Photo attachment file must not be more than 5000KB (5MB).',
        ];
    }
}
