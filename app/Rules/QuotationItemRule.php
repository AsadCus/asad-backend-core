<?php

namespace App\Rules;

class QuotationItemRule
{
    public function rules(string $prefix = 'items')
    {
        return [
            "$prefix" => ['required', 'array', 'min:1'],
            "$prefix.*._key" => ['required', 'string'],
            "$prefix.*.id" => ['nullable'],
            "$prefix.*.parent_key" => ['nullable', 'string'],
            "$prefix.*.parent_id" => ['nullable'],
            "$prefix.*.description" => ['required', 'string'],
            "$prefix.*.is_header" => ['nullable', 'boolean'],
            "$prefix.*.is_optional" => ['nullable', 'boolean'],
            "$prefix.*.quantity" => ['nullable', "required_if:$prefix.*.is_header,false", 'numeric'],
            "$prefix.*.rate" => ['nullable', "required_if:$prefix.*.is_header,false", 'numeric'],
            "$prefix.*.sort_order" => ['nullable', 'numeric'],
        ];
    }
}
