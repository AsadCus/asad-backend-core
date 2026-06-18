<?php

namespace App\Services;

use App\Models\ApprovalMatrix;
use Illuminate\Support\Facades\DB;

class ApprovalMatrixService
{
    public function getForDataTable()
    {
        return ApprovalMatrix::query()->orderBy('submitter_level')->get()->map(fn ($q) => [
            'id' => $q->id,
            'submitter_level' => $q->submitter_level,
            'approver_1_level' => $q->approver_1_level,
            'approver_2_level' => $q->approver_2_level,
            'final_verifier_role' => $q->final_verifier_role,
        ]);
    }

    public function getForEditShow($id)
    {
        $matrix = ApprovalMatrix::findOrFail($id);

        return [
            'id' => $matrix->id,
            'submitter_level' => $matrix->submitter_level,
            'approver_1_level' => $matrix->approver_1_level,
            'approver_2_level' => $matrix->approver_2_level,
            'final_verifier_role' => $matrix->final_verifier_role,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $matrix = ApprovalMatrix::create($data);

            activity()->performedOn($matrix)->log('Approval matrix created successfully #'.($matrix->id ?? null));

            return $matrix;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $matrix = ApprovalMatrix::findOrFail($id);
            $matrix->update($data);

            activity()->performedOn($matrix)->log('Approval matrix updated successfully #'.($matrix->id ?? null));

            return $matrix;
        });
    }

    public function delete($id)
    {
        $matrix = ApprovalMatrix::find($id);

        if (! $matrix) {
            return false;
        }

        $matrix->delete();

        activity()->performedOn($matrix)->log('Approval matrix deleted successfully #'.($matrix->id ?? null));

        return true;
    }
}
