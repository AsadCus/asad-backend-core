<?php

namespace App\Rules;

use App\Enums\AttendanceCorrectionType;
use Illuminate\Validation\Rule;

class AttendanceCorrectionRule
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function storeRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'correction_type' => ['required', Rule::in(AttendanceCorrectionType::values())],
            'reason' => ['required', 'string', 'max:1000'],
            'requested_check_in' => ['nullable', 'date'],
            'requested_check_out' => ['nullable', 'date'],
            'attendance_id' => ['nullable', 'integer', 'exists:attendances,id'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function decisionRules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
