<?php

namespace App\Rules;

class QuotationRule
{
    public function rules()
    {
        return array_merge(
            [
                'customer_id' => ['nullable', 'exists:customers,id'],
                'quotation_date' => ['nullable', 'string'],
                'expiry_date' => ['nullable', 'string'],
                'payment_plan' => ['nullable', 'string'],
                'payment_method' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'status' => ['nullable', 'in:draft,sent,revised,ready,accepted,converted,rejected,expired,cancelled'],
            ],
            (new QuotationItemRule)->rules('items')
        );
    }

    public function sentRules()
    {
        return array_merge(
            [
                'customer_id' => ['required', 'exists:customers,id'],
                'quotation_date' => ['required', 'string'],
                'expiry_date' => ['required', 'string'],
                'payment_plan' => ['required', 'string'],
                'payment_method' => ['required', 'string'],
                'description' => ['required', 'string'],
                'status' => ['required', 'in:draft,sent,revised,ready,accepted,converted,rejected,expired,cancelled'],
            ],
            (new QuotationItemRule)->rules('items')
        );
    }
}
