<?php

namespace App\Rules;

class FinancialYearRule
{
    public function rules()
    {
        $rules = [
            'start_day' => 'required|integer|min:1|max:31',
            'start_month' => 'required|integer|min:1|max:12',
            'end_day' => 'required|integer|min:1|max:31',
            'end_month' => 'required|integer|min:1|max:12',
        ];

        return $rules;
    }
}
