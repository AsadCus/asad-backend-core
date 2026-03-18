<?php

namespace App\Rules;

use App\Enums\QuotationStatus;

class QuotationRule
{
    private function statusRule(): string
    {
        return 'in:'.implode(',', QuotationStatus::values());
    }

    public function rules()
    {
        return array_merge(
            [
                'customer_id' => ['nullable', 'exists:customers,id'],
                'customer_confirmation_id' => ['nullable', 'exists:customer_confirmations,id'],
                'quotation_date' => ['nullable', 'string'],
                'expiry_date' => ['nullable', 'string'],
                'payment_plan' => ['nullable', 'string'],
                'payment_method' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'status' => ['nullable', $this->statusRule()],
                'extensions' => ['nullable', 'array'],
                'extensions.*.id' => ['nullable', 'integer'],
                'extensions.*.name' => ['required_with:extensions', 'string', 'max:255'],
                'extensions.*.type' => ['required_with:extensions', 'string', 'max:100'],
                'extensions.*.amount' => ['required_with:extensions', 'numeric'],
                'extensions.*.sort_order' => ['nullable', 'integer'],
            ],
            (new QuotationItemRule)->rules('items')
        );
    }

    public function sentRules()
    {
        return array_merge(
            [
                'customer_id' => ['required', 'exists:customers,id'],
                'customer_confirmation_id' => ['nullable', 'exists:customer_confirmations,id'],
                'quotation_date' => ['required', 'string'],
                'expiry_date' => ['required', 'string'],
                'payment_plan' => ['required', 'string'],
                'payment_method' => ['required', 'string'],
                'description' => ['required', 'string'],
                'status' => ['required', $this->statusRule()],
                'extensions' => ['nullable', 'array'],
                'extensions.*.id' => ['nullable', 'integer'],
                'extensions.*.name' => ['required_with:extensions', 'string', 'max:255'],
                'extensions.*.type' => ['required_with:extensions', 'string', 'max:100'],
                'extensions.*.amount' => ['required_with:extensions', 'numeric'],
                'extensions.*.sort_order' => ['nullable', 'integer'],
            ],
            (new QuotationItemRule)->rules('items')
        );
    }
}
