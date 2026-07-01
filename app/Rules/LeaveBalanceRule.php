<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class LeaveBalanceRule
{
    public function rules(?string $id = null): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            // Compound uniqueness (employee + leave type + year) mirrors the DB constraint.
            'leave_type_id' => [
                'required', 'integer', Rule::exists('leave_types', 'id')->where('requires_balance', true),
                Rule::unique('leave_balances', 'leave_type_id')
                    ->where(fn ($q) => $q->where('employee_id', request('employee_id'))->where('year', request('year')))
                    ->ignore($id),
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'allocated' => ['required', 'numeric', 'min:0', 'max:999'],
            'used' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function importRules(): array
    {
        // ponytail: `zip` is allowed because xlsx is a zip container and finfo reports it
        // as application/zip on some hosts; the reader rejects non-spreadsheet files (422).
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,zip', 'max:5120'],
        ];
    }
}
