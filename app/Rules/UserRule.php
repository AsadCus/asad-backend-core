<?php

namespace App\Rules;

class UserRule
{
    public function rules(string $role, ?string $action = null, ?string $id = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|string|in:admin,sales,supplier,customer',
        ];

        if ($action === 'update') {
            $rules['email'] = 'required|email|unique:users,email,'.$id;
        }

        if ($role === 'sales') {
            $rules['branch_id'] = 'required';
        }

        if ($role === 'supplier') {
            $rules['company_name'] = 'required|string|max:255';
            $rules['address'] = 'required|string|max:255';
        }

        if ($role === 'customer') {
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
            $rules['passport_file'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
            $rules['photo_file'] = 'nullable|file|mimes:jpg,jpeg,png|max:5120';
            $rules['passport_path'] = 'nullable|string';
            $rules['photo_path'] = 'nullable|string';
            $rules['branch_id'] = 'nullable';
            $rules['handled_by'] = 'nullable';
        }

        return $rules;
    }
}
