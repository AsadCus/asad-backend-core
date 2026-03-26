<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class OrderRule
{
    public function rules(?int $orderId = null): array
    {
        return array_merge(
            [
                'order_number' => ['nullable', 'string', 'max:100'],
                'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
                'quotation_id' => ['required', 'exists:quotations,id', Rule::unique('orders', 'quotation_id')->ignore($orderId)],
                'payment_plan' => ['required', Rule::in(['direct', 'full', 'installment'])],
            ],
            (new InvoiceRule)->rules('invoices')
        );
    }
}
