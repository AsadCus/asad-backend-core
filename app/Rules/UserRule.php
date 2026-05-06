<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class UserRule
{
    public function rules(string $role, ?string $action = null, ?string $id = null): array
    {
        $scopeMode = strtolower((string) config('data_scope.mode', 'country'));

        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'contact' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|string|in:superadmin,admin,sales,operations,customer',
        ];

        if ($action === 'update') {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ];
        }

        if (in_array($role, ['superadmin', 'admin', 'sales', 'operations'], true)) {
            $rules['scope_ids'] = 'required|array|min:1';
            $rules['scope_ids.*'] = $scopeMode === 'branch'
                ? 'required|integer|exists:branches,id'
                : 'required|integer|exists:countries,id';
        }

        if ($role === 'customer') {
            $rules['customer_number'] = 'nullable|string|max:100';
            $rules['number_format_id'] = 'nullable|integer|exists:numbering_formats,id';
            $rules['nric_number'] = 'nullable|string';
            $rules['address'] = 'nullable|string|max:500';
            $rules['nationality'] = 'nullable|string|max:100';
            $rules['passport_number'] = 'nullable|string|max:50';
            $rules['passport_issue_date'] = 'nullable|date';
            $rules['passport_expiry_date'] = 'nullable|date';
            $rules['passport_place_of_issue'] = 'nullable|string|max:255';
            $rules['gender'] = 'nullable|string|in:male,female';
            $rules['marital_status'] = 'nullable|string|in:single,married,divorced,widowed';
            $rules['date_of_birth'] = 'nullable|date';
            $rules['place_of_birth'] = 'nullable|string|max:255';
            $rules['first_time_umrah'] = 'nullable|boolean';
            $rules['has_chronic_disease'] = 'nullable|boolean';
            $rules['is_using_wheelchair'] = 'nullable|boolean';
            $rules['chronic_disease_details'] = 'nullable|string|max:1000';

            $rules['passport_documents'] = ['nullable', 'array'];
            $rules['passport_documents.*.id'] = ['nullable', 'integer'];
            $rules['passport_documents.*.file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
            $rules['passport_documents.*.file_name'] = ['nullable', 'string', 'max:255'];
            $rules['passport_documents.*.file_path'] = ['nullable', 'string', 'max:255'];
            $rules['passport_documents.*.removed'] = ['nullable', 'boolean'];
            $rules['photo_documents'] = ['nullable', 'array'];
            $rules['photo_documents.*.id'] = ['nullable', 'integer'];
            $rules['photo_documents.*.file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'];
            $rules['photo_documents.*.file_name'] = ['nullable', 'string', 'max:255'];
            $rules['photo_documents.*.file_path'] = ['nullable', 'string', 'max:255'];
            $rules['photo_documents.*.removed'] = ['nullable', 'boolean'];

            $rules['scope_ids'] = 'nullable|array';
            $rules['scope_ids.*'] = 'nullable|integer';
        }

        return $rules;
    }
}
