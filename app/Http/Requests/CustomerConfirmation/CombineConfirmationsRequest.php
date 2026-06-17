<?php

namespace App\Http\Requests\CustomerConfirmation;

use Illuminate\Foundation\Http\FormRequest;

class CombineConfirmationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_confirmation_id' => ['required', 'integer', 'exists:customer_confirmations,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:customer_confirmation_members,id'],
            'target_quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
        ];
    }
}
