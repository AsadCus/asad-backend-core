<?php

namespace App\Rules;

class InvoiceRule
{
    public function rules(string $prefix = 'invoices')
    {
        return array_merge(
            [
                "$prefix" => ['required', 'array', 'min:1'],
                "$prefix.*._key" => ['required', 'string'],
                "$prefix.*.description" => ['required', 'string'],
                "$prefix.*.amount" => ['required', 'numeric'],
                "$prefix.*.invoice_date" => ['required', 'date'],
                "$prefix.*.due_date" => ['required', 'date', 'after_or_equal:'.$prefix.'.*.invoice_date'],
                "$prefix.*.status" => ['nullable', 'in:draft,issued,paid,overdue,cancelled'],
            ],
            (new QuotationItemRule)->rules("$prefix.*.items")
        );
    }
}
