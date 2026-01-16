<?php

namespace App\Rules;

class FinancialYearRule
{
    public function rules()
    {
        $rules = [
            'year' => 'required|string',
            'default' => 'nullable|boolean',
        ];

        return $rules;
    }
}
