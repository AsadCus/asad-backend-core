<?php

namespace App\Rules;

use App\Support\InvoiceStatus;
use Illuminate\Validation\Rule;

class InvoiceRule
{
    public function singleRules(): array
    {
        return array_merge([
            '_key' => ['nullable', 'string'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
            'description' => ['required', 'string'],
            'payment_method' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'status' => ['nullable', Rule::in(InvoiceStatus::userSelectableValues())],
            'extensions' => ['nullable', 'array'],
            'extensions.*.id' => ['nullable'],
            'extensions.*.quotation_extension_master_id' => ['nullable', 'integer', 'exists:quotation_extension_masters,id'],
            'extensions.*.name' => ['required_with:extensions', 'string', 'max:255'],
            'extensions.*.type' => ['required_with:extensions', 'string', 'max:100'],
            'extensions.*.calculation_mode' => ['nullable', 'string', 'in:fixed,percentage'],
            'extensions.*.calculation_value' => ['nullable', 'numeric'],
            'extensions.*.amount' => ['nullable', 'numeric'],
            'extensions.*.sort_order' => ['nullable', 'integer'],
        ], (new QuotationItemRule)->rules('items'));
    }

    public function rules(string $prefix = 'invoices')
    {
        return array_merge(
            [
                "$prefix" => ['required', 'array', 'min:1'],
                "$prefix.*._key" => ['required', 'string'],
                "$prefix.*.invoice_number" => ['nullable', 'string', 'max:100'],
                "$prefix.*.number_format_id" => ['nullable', 'integer', 'exists:numbering_formats,id'],
                "$prefix.*.description" => ['required', 'string'],
                "$prefix.*.payment_method" => ['required', 'string', 'max:100'],
                "$prefix.*.amount" => ['required', 'numeric'],
                "$prefix.*.invoice_date" => ['required', 'date'],
                "$prefix.*.due_date" => ['required', 'date', 'after_or_equal:'.$prefix.'.*.invoice_date'],
                "$prefix.*.status" => ['nullable', Rule::in(InvoiceStatus::userSelectableValues())],
                "$prefix.*.extensions" => ['nullable', 'array'],
                "$prefix.*.extensions.*.id" => ['nullable'],
                "$prefix.*.extensions.*.quotation_extension_master_id" => ['nullable', 'integer', 'exists:quotation_extension_masters,id'],
                "$prefix.*.extensions.*.name" => ['required_with:'.$prefix.'.*.extensions', 'string', 'max:255'],
                "$prefix.*.extensions.*.type" => ['required_with:'.$prefix.'.*.extensions', 'string', 'max:100'],
                "$prefix.*.extensions.*.calculation_mode" => ['nullable', 'string', 'in:fixed,percentage'],
                "$prefix.*.extensions.*.calculation_value" => ['nullable', 'numeric'],
                "$prefix.*.extensions.*.amount" => ['nullable', 'numeric'],
                "$prefix.*.extensions.*.sort_order" => ['nullable', 'integer'],
            ],
            (new QuotationItemRule)->rules("$prefix.*.items")
        );
    }
}
