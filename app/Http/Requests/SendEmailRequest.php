<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendEmailRequest extends FormRequest
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
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'template' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
