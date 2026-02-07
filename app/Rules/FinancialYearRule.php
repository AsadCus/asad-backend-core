<?php

namespace App\Rules;

class FinancialYearRule
{
    public function rules()
    {
        $rules = [
            'year' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'default' => 'nullable|boolean',
        ];

        return $rules;
    }
}
