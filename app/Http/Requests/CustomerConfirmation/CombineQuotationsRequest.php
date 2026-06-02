<?php

namespace App\Http\Requests\CustomerConfirmation;

use Illuminate\Foundation\Http\FormRequest;

class CombineQuotationsRequest extends FormRequest
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
            'target_quotation_id' => ['required', 'integer', 'exists:quotations,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:customer_confirmation_members,id'],
        ];
    }
}
