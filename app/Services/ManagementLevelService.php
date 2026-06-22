<?php

namespace App\Services;

use App\Models\ManagementLevel;
use Illuminate\Support\Facades\DB;

class ManagementLevelService
{
    public function getForDataTable()
    {
        return ManagementLevel::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (ManagementLevel $l) => $this->toRow($l));
    }

    public function getForFilter()
    {
        return ManagementLevel::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (ManagementLevel $l) => [
            'value' => $l->id,
            'label' => $l->name,
        ]);
    }

    public function getForEditShow($id): array
    {
        return $this->toRow(ManagementLevel::findOrFail($id));
    }

    public function store(array $data): ManagementLevel
    {
        return DB::transaction(function () use ($data) {
            $level = ManagementLevel::create($data);
            activity()->performedOn($level)->log('Management level created successfully #'.($level->id ?? null));

            return $level;
        });
    }

    public function update(array $data, $id): ManagementLevel
    {
        return DB::transaction(function () use ($data, $id) {
            $level = ManagementLevel::findOrFail($id);
            $level->update($data);
            activity()->performedOn($level)->log('Management level updated successfully #'.($level->id ?? null));

            return $level;
        });
    }

    public function delete($id): bool
    {
        $level = ManagementLevel::find($id);

        if (! $level) {
            return false;
        }

        $level->delete();
        activity()->performedOn($level)->log('Management level deleted successfully #'.($level->id ?? null));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(ManagementLevel $l): array
    {
        return [
            'id' => $l->id,
            'name' => $l->name,
            'code' => $l->code,
            'color' => $l->color,
            'sort_order' => $l->sort_order,
            'is_active' => (bool) $l->is_active,
        ];
    }
}
