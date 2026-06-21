<?php

namespace App\Http\Requests\Account;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nik' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'birth_date' => ['nullable', 'date'],
            'religion_id' => ['nullable', 'integer', 'exists:religions,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
