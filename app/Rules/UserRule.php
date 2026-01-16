<?php

namespace App\Rules;

class UserRule
{
    public function rules($role, $action = null, $id = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|string|in:admin,sales,supplier,customer',
        ];

        if ($action === 'update') {
            $rules['email'] = 'required|email|unique:users,email,' . $id;
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
            $rules['address'] = 'nullable|string|max:255';
            $rules['branch_id'] = 'required';
            $rules['handled_by'] = 'nullable';
            $rules['age_preferences'] = 'required|array';
            $rules['country_preferences'] = 'required|array';
            $rules['experience_preferences'] = 'required|array';
            // $rules['maids'] = 'nullable|array';
            // $rules['maids.*'] = 'exists:maids,id';
        }

        return $rules;
    }
}
