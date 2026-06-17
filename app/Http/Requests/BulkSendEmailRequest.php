<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSendEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:25'],
            'ids.*' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'template' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
