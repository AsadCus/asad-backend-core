<?php

namespace App\Rules;

class CountryRule
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'adjective' => ['nullable', 'string', 'max:255'],
            'currency_symbol' => ['nullable', 'string', 'max:16'],
        ];
    }
}
