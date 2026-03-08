<?php

namespace App\Services;

use App\Models\Branch;
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
        $branch = Branch::find($id);
        if (! $branch) {
            return false;
        }

        return activity()
                ->performedOn($branch)
                ->withProperties(['subject_type' => 'Branch', 'subject_id' => $branch->id ?? null])
                ->log('Branch deleted successfully #'.($branch->id ?? null));

            $branch->delete();
    }
}
