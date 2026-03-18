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
            'signature_stamp_layout' => ['nullable', 'string', 'in:default,custom'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'logo_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'stamp_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'custom_stamp_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'custom_signature_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'custom_signature_data' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string'], // Allow empty string for deletion signal
            'stamp_path' => ['nullable', 'string'], // Allow empty string for deletion signal
            'signature_path' => ['nullable', 'string'], // Allow empty string for deletion signal
            'custom_stamp_path' => ['nullable', 'string'],
            'custom_signature_path' => ['nullable', 'string'],
            'custom_signature_stamp_layout' => ['nullable', 'array'],
            'custom_signature_stamp_layout.unit' => ['nullable', 'string', 'in:percent,px'],
            'custom_signature_stamp_layout.placement' => ['nullable', 'string', 'in:left_side,right_side,stack_each_other,up_side,down_side'],
            'custom_signature_stamp_layout.labels' => ['nullable', 'array'],
            'custom_signature_stamp_layout.labels.show_name' => ['nullable', 'boolean'],
            'custom_signature_stamp_layout.labels.show_date' => ['nullable', 'boolean'],
            'custom_signature_stamp_layout.labels.full_name' => ['nullable', 'string', 'max:255'],
            'custom_signature_stamp_layout.labels.stamp_name' => ['nullable', 'string', 'max:255'],
            'custom_signature_stamp_layout.labels.signature_name' => ['nullable', 'string', 'max:255'],
            'custom_signature_stamp_layout.labels.date' => ['nullable', 'date'],
            'custom_signature_stamp_layout.stamp' => ['nullable', 'array'],
            'custom_signature_stamp_layout.signature' => ['nullable', 'array'],
            'custom_signature_stamp_layout.stamp.x' => ['nullable', 'numeric', 'min:0'],
            'custom_signature_stamp_layout.stamp.y' => ['nullable', 'numeric', 'min:0'],
            'custom_signature_stamp_layout.stamp.width' => ['nullable', 'numeric', 'min:1'],
            'custom_signature_stamp_layout.stamp.height' => ['nullable', 'numeric', 'min:1'],
            'custom_signature_stamp_layout.stamp.z' => ['nullable', 'integer', 'min:0'],
            'custom_signature_stamp_layout.stamp.name' => ['nullable', 'string', 'max:255'],
            'custom_signature_stamp_layout.signature.x' => ['nullable', 'numeric', 'min:0'],
            'custom_signature_stamp_layout.signature.y' => ['nullable', 'numeric', 'min:0'],
            'custom_signature_stamp_layout.signature.width' => ['nullable', 'numeric', 'min:1'],
            'custom_signature_stamp_layout.signature.height' => ['nullable', 'numeric', 'min:1'],
            'custom_signature_stamp_layout.signature.z' => ['nullable', 'integer', 'min:0'],
            'custom_signature_stamp_layout.signature.name' => ['nullable', 'string', 'max:255'],
            'custom_signature_stamp_layout.signature.date' => ['nullable', 'date'],

            // Per-module template settings (wildcard for all modules including custom)
            'module_templates' => ['nullable', 'array'],
            'module_templates.*' => ['nullable', 'array'],
            'module_templates.*.footer_text' => ['nullable', 'string', 'max:2000'],
            'module_templates.*.show_stamp' => ['nullable', 'boolean'],
            'module_templates.*.show_signature' => ['nullable', 'boolean'],
            'module_templates.*.show_signature_stamp_name' => ['nullable', 'boolean'],
            'module_templates.*.show_signature_stamp_date' => ['nullable', 'boolean'],
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
            'signature_stamp_layout.in' => 'Signature and stamp layout must be either default or custom.',
            'custom_signature_stamp_layout.placement.in' => 'Placement must be left side, right side, stack each other, up side, or down side.',
            'logo_file.image' => 'Logo must be an image file.',
            'logo_file.max' => 'Logo file size must not exceed 2MB.',
            'stamp_file.image' => 'Stamp must be an image file.',
            'stamp_file.max' => 'Stamp file size must not exceed 2MB.',
            'signature_file.image' => 'Signature must be an image file.',
            'signature_file.max' => 'Signature file size must not exceed 2MB.',
            'custom_stamp_file.image' => 'Custom stamp must be an image file.',
            'custom_stamp_file.max' => 'Custom stamp file size must not exceed 2MB.',
            'custom_signature_file.image' => 'Custom signature must be an image file.',
            'custom_signature_file.max' => 'Custom signature file size must not exceed 2MB.',
        ];
    }
}
