<?php

namespace App\Rules;

class EnquiryRemarkRule
{
    public function rules(): array
    {
        return [
            'remark' => ['required', 'string', 'max:2000'],
        ];
    }

    public function updateRules(): array
    {
        return [
            'remark' => ['required', 'string', 'max:2000'],
        ];
    }
}
