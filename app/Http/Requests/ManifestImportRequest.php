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
            'context.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'context.sales_id' => ['nullable', 'integer', 'exists:users,id'],

            // Members sheet (one row per person). Referential checks (payer_ref
            // resolution, member_key uniqueness, reconciliation) live in
            // ManifestImportService so they can be reported per booking.
            'members' => ['required', 'array', 'min:1'],
            'members.*.member_key' => ['nullable', 'string', 'max:100'],
            'members.*.booking_ref' => ['nullable', 'string', 'max:100'],
            'members.*.payer_ref' => ['nullable', 'string', 'max:100'],
            'members.*.sharing_group_key' => ['nullable', 'string', 'max:100'],
            'members.*.relationship' => ['nullable', 'string', 'max:100'],
            'members.*.name' => ['required', 'string', 'max:255'],
            'members.*.email' => ['nullable', 'email', 'max:255'],
            'members.*.contact' => ['nullable', 'string', 'max:50'],
            'members.*.nric_number' => ['nullable', 'string', 'max:50'],
            'members.*.passport_number' => ['nullable', 'string', 'max:50'],
            'members.*.passport_issue_date' => ['nullable', 'string'],
            'members.*.passport_expiry_date' => ['nullable', 'string'],
            'members.*.passport_place_of_issue' => ['nullable', 'string', 'max:255'],
            'members.*.nationality' => ['nullable', 'string', 'max:100'],
            // Kept tolerant on purpose: the dialog lowercases this, and a stray
            // value must fail the row (per-booking), not 422 the whole upload.
            'members.*.gender' => ['nullable', 'string', 'max:20'],
            'members.*.date_of_birth' => ['nullable', 'string'],
            'members.*.address' => ['nullable', 'string'],
            'members.*.sharing_plan' => ['required', 'string'],
            'members.*.is_leader' => ['nullable'],
            'members.*.has_chronic_disease' => ['nullable'],
            'members.*.is_using_wheelchair' => ['nullable'],

            // Payments sheet (one row per installment invoice).
            'payments' => ['nullable', 'array'],
            'payments.*.booking_ref' => ['required_with:payments', 'string', 'max:100'],
            'payments.*.payer_ref' => ['nullable', 'string', 'max:100'],
            'payments.*.installment_no' => ['nullable', 'integer', 'min:1'],
            'payments.*.invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.invoice_date' => ['nullable', 'string'],
            'payments.*.due_date' => ['nullable', 'string'],
            'payments.*.paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.paid_date' => ['nullable', 'string'],
            'payments.*.payment_method' => ['nullable', 'string', 'max:50'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
