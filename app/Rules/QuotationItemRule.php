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
            "$prefix.*.customer_confirmation_member_id" => ['nullable', 'integer', 'exists:customer_confirmation_members,id'],
            "$prefix.*.sharing_plan" => ['nullable', 'string', 'in:single,double,triple,quad'],
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
