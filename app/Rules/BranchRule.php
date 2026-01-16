<?php

namespace App\Rules;

class BranchRule
{
    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'country_id' => 'required',
        ];

        return $rules;
    }
}
