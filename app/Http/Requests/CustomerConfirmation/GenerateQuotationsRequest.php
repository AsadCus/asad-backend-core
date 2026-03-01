<?php

namespace App\Http\Requests\CustomerConfirmation;

use App\Rules\PayerMemberMappingBelongsToConfirmation;
use Illuminate\Foundation\Http\FormRequest;

class GenerateQuotationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payer_to_members' => [
                'required',
                'array',
                'min:1',
                new PayerMemberMappingBelongsToConfirmation((int) $this->route('id')),
            ],
            'payer_to_members.*' => ['required', 'array', 'min:1'],
            'payer_to_members.*.*' => ['integer', 'exists:customer_confirmation_members,id'],
        ];
    }
}
