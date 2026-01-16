<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class OrderRule
{
    public function rules(?int $orderId = null): array
    {
        return array_merge(
            [
                'quotation_id' => ['required', 'exists:quotations,id', Rule::unique('orders', 'quotation_id')->ignore($orderId)],
                'payment_plan' => ['required', Rule::in(['direct', 'full', 'installment'])],
                'handover_date' => ['required', 'date'],
            ],
            (new InvoiceRule())->rules('invoices')
        );
    }
}
