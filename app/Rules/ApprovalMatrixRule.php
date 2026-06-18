<?php

namespace App\Rules;

use App\Enums\PositionLevel;
use Illuminate\Validation\Rule;

class ApprovalMatrixRule
{
    public function rules(?string $id = null): array
    {
        return [
            'submitter_level' => ['required', Rule::in(PositionLevel::values()), Rule::unique('approval_matrices', 'submitter_level')->ignore($id)],
            'approver_1_level' => ['required', Rule::in(PositionLevel::values())],
            'approver_2_level' => ['nullable', Rule::in(PositionLevel::values())],
            'final_verifier_role' => ['required', 'string', 'max:50'],
        ];
    }
}
