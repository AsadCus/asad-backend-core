<?php

namespace App\Rules;

class FinancialYearRule
{
    public function rules()
    {
        $rules = [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ];

        return $rules;
    }
}
