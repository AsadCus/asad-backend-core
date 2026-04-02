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
                'quotation_number' => ['nullable', 'string', 'max:100'],
                'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
                'customer_id' => ['required', 'exists:customers,id'],
                'customer_confirmation_id' => ['nullable', 'integer', 'exists:customer_confirmations,id'],
                'quotation_date' => ['nullable', 'string'],
                'expiry_date' => ['nullable', 'string'],
                'payment_plan' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'status' => ['nullable', $this->statusRule()],
                'extensions' => ['nullable', 'array'],
                'extensions.*.id' => ['nullable', 'integer'],
                'extensions.*.quotation_extension_master_id' => ['nullable', 'integer', 'exists:quotation_extension_masters,id'],
                'extensions.*.name' => ['required_with:extensions', 'string', 'max:255'],
                'extensions.*.type' => ['required_with:extensions', 'string', 'max:100'],
                'extensions.*.calculation_mode' => ['nullable', 'string', 'in:fixed,percentage'],
                'extensions.*.calculation_value' => ['nullable', 'numeric'],
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
                'quotation_number' => ['nullable', 'string', 'max:100'],
                'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
                'customer_id' => ['required', 'exists:customers,id'],
                'customer_confirmation_id' => ['nullable', 'integer', 'exists:customer_confirmations,id'],
                'quotation_date' => ['required', 'string'],
                'expiry_date' => ['required', 'string'],
                'payment_plan' => ['required', 'string'],
                'description' => ['required', 'string'],
                'status' => ['required', $this->statusRule()],
                'extensions' => ['nullable', 'array'],
                'extensions.*.id' => ['nullable', 'integer'],
                'extensions.*.quotation_extension_master_id' => ['nullable', 'integer', 'exists:quotation_extension_masters,id'],
                'extensions.*.name' => ['required_with:extensions', 'string', 'max:255'],
                'extensions.*.type' => ['required_with:extensions', 'string', 'max:100'],
                'extensions.*.calculation_mode' => ['nullable', 'string', 'in:fixed,percentage'],
                'extensions.*.calculation_value' => ['nullable', 'numeric'],
                'extensions.*.amount' => ['required_with:extensions', 'numeric'],
                'extensions.*.sort_order' => ['nullable', 'integer'],
            ],
            (new QuotationItemRule)->rules('items')
        );
    }
}
