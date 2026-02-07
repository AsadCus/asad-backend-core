<?php

namespace App\Rules;

class QuotationRule
{
    public function rules()
    {
        return array_merge(
            [
                'customer_id' => ['nullable', 'exists:customers,id'],
                'maid_id' => ['nullable', 'exists:maids,id', 'required_if:status,sent'],
                'quotation_date' => ['nullable', 'string'],
                'expiry_date' => ['nullable', 'string'],
                'commencement_date' => ['nullable', 'string'],
                'monthly_salary' => ['nullable', 'numeric'],
                'loan_duration' => ['nullable', 'numeric'],
                'rest_day_of_the_week' => ['nullable', 'array'],
                'rest_days_per_month' => ['nullable', 'numeric'],
                'compensation_off_in_lieu' => ['nullable', 'numeric'],
                'payment_plan' => ['nullable', 'string'],
                'payment_method' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'status' => ['nullable', 'in:draft,sent,revised,ready,accepted,converted,rejected,expired,cancelled'],
            ],
            (new QuotationItemRule())->rules('items')
        );
    }

    public function sentRules()
    {
        return array_merge(
            [
                'customer_id' => ['required', 'exists:customers,id'],
                'maid_id' => ['required', 'exists:maids,id'],
                'quotation_date' => ['required', 'string'],
                'expiry_date' => ['required', 'string'],
                'commencement_date' => ['required', 'string'],
                'monthly_salary' => ['required', 'numeric'],
                'loan_duration' => ['required', 'numeric'],
                'rest_day_of_the_week' => ['required', 'array'],
                'rest_days_per_month' => ['required', 'numeric'],
                'compensation_off_in_lieu' => ['required', 'numeric'],
                'payment_plan' => ['required', 'string'],
                'payment_method' => ['required', 'string'],
                'description' => ['required', 'string'],
                'status' => ['required', 'in:draft,sent,revised,ready,accepted,converted,rejected,expired,cancelled'],
            ],
            (new QuotationItemRule())->rules('items')
        );
    }
}
