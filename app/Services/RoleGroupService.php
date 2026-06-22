<?php

namespace App\Services;

use App\Models\RoleGroup;
use Illuminate\Support\Facades\DB;

class RoleGroupService
{
    public function getForDataTable()
    {
        return RoleGroup::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (RoleGroup $g) => $this->toRow($g));
    }

    public function getForFilter()
    {
        return RoleGroup::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (RoleGroup $g) => [
            'value' => $g->id,
            'label' => $g->name,
        ]);
    }

    public function getForEditShow($id): array
    {
        return $this->toRow(RoleGroup::findOrFail($id));
    }

    public function store(array $data): RoleGroup
    {
        return DB::transaction(function () use ($data) {
            $group = RoleGroup::create($data);
            activity()->performedOn($group)->log('Role group created successfully #'.($group->id ?? null));

            return $group;
        });
    }

    public function update(array $data, $id): RoleGroup
    {
        return DB::transaction(function () use ($data, $id) {
            $group = RoleGroup::findOrFail($id);
            $group->update($data);
            activity()->performedOn($group)->log('Role group updated successfully #'.($group->id ?? null));

            return $group;
        });
    }

    public function delete($id): bool
    {
        $group = RoleGroup::find($id);

        if (! $group) {
            return false;
        }

        $group->delete();
        activity()->performedOn($group)->log('Role group deleted successfully #'.($group->id ?? null));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(RoleGroup $g): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'code' => $g->code,
            'description' => $g->description,
            'color' => $g->color,
            'sort_order' => $g->sort_order,
            'is_active' => (bool) $g->is_active,
        ];
    }
}
