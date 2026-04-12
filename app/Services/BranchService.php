<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Operation;
use App\Models\Sales;
use Illuminate\Support\Facades\DB;

class BranchService
{
    public function get()
    {
        $data = Branch::get();

        return $data;
    }

    public function getForDataTable()
    {
        $data = Branch::get()->map(function ($q) {
            return [
                'id' => $q->id,
                'name' => $q->name,
                'country_id' => $q->country->id,
                'country_name' => $q->country->name,
            ];
        });

        return $data;
    }

    public function getForFilter()
    {
        $data = Branch::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->name,
            ];
        });

        return $data;
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::create([
                'name' => $data['name'],
                'country_id' => $data['country_id'],
            ]);

            activity()
                ->performedOn($branch)
                ->withProperties(['subject_type' => 'Branch', 'subject_id' => $branch->id ?? null])
                ->log('Branch created successfully #'.($branch->id ?? null));

            return $branch;
        });
    }

    public function getForEditShow($id)
    {
        $branch = Branch::findOrFail($id);

        $data = [
            'id' => $branch->id,
            'name' => $branch->name,
            'country_id' => $branch->country_id,
        ];

        return $data;
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $branch = Branch::findOrFail($id);

            $branch->update([
                'name' => $data['name'],
                'country_id' => $data['country_id'],
            ]);

            activity()
                ->performedOn($branch)
                ->withProperties(['subject_type' => 'Branch', 'subject_id' => $branch->id ?? null])
                ->log('Branch updated successfully #'.($branch->id ?? null));

            return $branch;
        });
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $branch = Branch::find($id);
            if (! $branch) {
                return false;
            }

            Customer::query()
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            Sales::query()
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            Enquiry::query()
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            Admin::query()
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            Operation::query()
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            $this->pruneBranchScopeListFromRoleAssignments($branch->id);

            $branch->delete();

            activity()
                ->performedOn($branch)
                ->withProperties(['subject_type' => 'Branch', 'subject_id' => $branch->id ?? null])
                ->log('Branch deleted successfully #'.($branch->id ?? null));

            return true;
        });
    }

    private function pruneBranchScopeListFromRoleAssignments(int $branchId): void
    {
        foreach (Admin::query()->whereJsonContains('branch_ids', $branchId)->get() as $admin) {
            $nextBranchIds = collect($admin->branch_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $branchId)
                ->values()
                ->all();

            $admin->update(['branch_ids' => $nextBranchIds]);
        }

        foreach (Operation::query()->whereJsonContains('branch_ids', $branchId)->get() as $operation) {
            $nextBranchIds = collect($operation->branch_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $branchId)
                ->values()
                ->all();

            $operation->update(['branch_ids' => $nextBranchIds]);
        }

        foreach (Sales::query()->whereJsonContains('branch_ids', $branchId)->get() as $sales) {
            $nextBranchIds = collect($sales->branch_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $branchId)
                ->values()
                ->all();

            $sales->update(['branch_ids' => $nextBranchIds]);
        }
    }
}
