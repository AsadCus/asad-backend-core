<?php

namespace App\Services;

use App\Models\OrgInfo;
use App\Models\OrgUnit;
use Illuminate\Support\Facades\DB;

class OrgInfoService
{
    /**
     * Sections from holding → … → the active unit, each with its info entries.
     * Units with no info are skipped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHierarchical(OrgUnit $active): array
    {
        $chain = [];
        $node = $active;
        while ($node !== null) {
            $chain[] = $node;
            $node = $node->parent;
        }
        $chain = array_reverse($chain); // root → active

        $sections = [];
        foreach ($chain as $unit) {
            $infos = $unit->orgInfos()->orderBy('sort_order')->orderBy('id')->get();
            if ($infos->isEmpty()) {
                continue;
            }
            $sections[] = [
                'org_unit' => $unit->toSummary(),
                'infos' => $infos->map(fn (OrgInfo $i) => $this->toRow($i))->all(),
            ];
        }

        return $sections;
    }

    public function store(array $data): OrgInfo
    {
        return DB::transaction(function () use ($data) {
            $info = OrgInfo::create($data);
            activity()->performedOn($info)->log('Company info created successfully #'.($info->id ?? null));

            return $info;
        });
    }

    public function update(array $data, $id): OrgInfo
    {
        return DB::transaction(function () use ($data, $id) {
            $info = OrgInfo::findOrFail($id);
            $info->update($data);
            activity()->performedOn($info)->log('Company info updated successfully #'.($info->id ?? null));

            return $info;
        });
    }

    public function delete($id): bool
    {
        $info = OrgInfo::find($id);

        if (! $info) {
            return false;
        }

        $info->delete();
        activity()->performedOn($info)->log('Company info deleted successfully #'.($info->id ?? null));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(OrgInfo $i): array
    {
        return [
            'id' => $i->id,
            'org_unit_id' => $i->org_unit_id,
            'title' => $i->title,
            'body' => $i->body,
            'sort_order' => $i->sort_order,
        ];
    }
}
