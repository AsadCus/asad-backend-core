<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManifestImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'context' => ['nullable', 'array'],
            'context.date_of_application' => ['nullable', 'string'],

            'data' => ['required', 'array', 'min:1'],
            'data.*.name' => ['required', 'string', 'max:255'],
            'data.*.email' => ['nullable', 'email', 'max:255'],
            'data.*.contact' => ['nullable', 'string', 'max:50'],
            'data.*.nric_number' => ['nullable', 'string', 'max:50'],
            'data.*.passport_number' => ['nullable', 'string', 'max:50'],
            'data.*.passport_issue_date' => ['nullable', 'string'],
            'data.*.passport_expiry_date' => ['nullable', 'string'],
            'data.*.passport_place_of_issue' => ['nullable', 'string', 'max:255'],
            'data.*.nationality' => ['nullable', 'string', 'max:100'],
            'data.*.gender' => ['nullable', 'string', 'in:male,female'],
            'data.*.date_of_birth' => ['nullable', 'string'],
            'data.*.address' => ['nullable', 'string'],
            'data.*.sharing_plan' => ['required', 'string'],
            'data.*.is_leader' => ['nullable'],
            'data.*.has_chronic_disease' => ['nullable'],
            'data.*.is_using_wheelchair' => ['nullable'],
            'data.*.invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'data.*.receipt_amount' => ['nullable', 'numeric', 'min:0'],
            'data.*.receipt_date' => ['nullable', 'string'],
            'data.*.receipt_method' => ['nullable', 'string', 'max:50'],
            'data.*.receipt_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
