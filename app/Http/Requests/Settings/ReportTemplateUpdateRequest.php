<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportTemplateUpdateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Global branding
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'logo_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'stamp_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'logo_path' => ['nullable', 'string'], // Allow empty string for deletion signal
            'stamp_path' => ['nullable', 'string'], // Allow empty string for deletion signal
            'signature_path' => ['nullable', 'string'], // Allow empty string for deletion signal

            // Per-module template settings (wildcard for all modules including custom)
            'module_templates' => ['nullable', 'array'],
            'module_templates.*' => ['nullable', 'array'],
            'module_templates.*.footer_text' => ['nullable', 'string', 'max:2000'],
            'module_templates.*.show_stamp' => ['nullable', 'boolean'],
            'module_templates.*.show_signature' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required.',
            'company_name.max' => 'Company name must not exceed 255 characters.',
            'company_email.email' => 'Please provide a valid email address.',
            'brand_color.regex' => 'Brand color must be a valid hex color (e.g. #c05427).',
            'logo_file.image' => 'Logo must be an image file.',
            'logo_file.max' => 'Logo file size must not exceed 2MB.',
            'stamp_file.image' => 'Stamp must be an image file.',
            'stamp_file.max' => 'Stamp file size must not exceed 2MB.',
            'signature_file.image' => 'Signature must be an image file.',
            'signature_file.max' => 'Signature file size must not exceed 2MB.',
        ];
    }
}
