<?php

namespace App\Services;

use App\Models\Position;
use Illuminate\Support\Facades\DB;

class PositionService
{
    public function getForDataTable()
    {
        return Position::query()->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'level' => $q->level?->value,
            'level_label' => $q->level?->label(),
            'description' => $q->description,
            'is_active' => (bool) $q->is_active,
        ]);
    }

    public function getForFilter()
    {
        return Position::query()->orderBy('name')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
        ]);
    }

    public function getForEditShow($id)
    {
        $position = Position::findOrFail($id);

        return [
            'id' => $position->id,
            'name' => $position->name,
            'code' => $position->code,
            'level' => $position->level?->value,
            'level_label' => $position->level?->label(),
            'description' => $position->description,
            'is_active' => (bool) $position->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $position = Position::create($data);

            activity()->performedOn($position)->log('Position created successfully #'.($position->id ?? null));

            return $position;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $position = Position::findOrFail($id);
            $position->update($data);

            activity()->performedOn($position)->log('Position updated successfully #'.($position->id ?? null));

            return $position;
        });
    }

    public function delete($id)
    {
        $position = Position::find($id);

        if (! $position) {
            return false;
        }

        $position->delete();

        activity()->performedOn($position)->log('Position deleted successfully #'.($position->id ?? null));

        return true;
    }
}
