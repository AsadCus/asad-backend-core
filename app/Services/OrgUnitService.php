<?php

namespace App\Services;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrgUnitService
{
    public function __construct(
        private UserRoleFileUploadService $fileUploads,
        private WorkScheduleAssignmentService $scheduleAssignments,
    ) {}

    public function getForDataTable()
    {
        return OrgUnit::query()
            ->with('parent:id,name')
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (OrgUnit $u) => $this->toRow($u));
    }

    public function getForFilter()
    {
        return OrgUnit::query()->orderBy('name')->get()->map(fn (OrgUnit $u) => [
            'value' => $u->id,
            'label' => $u->name.' ('.$u->type->label().')',
            'type' => $u->type->value,
        ]);
    }

    /**
     * Nested tree for the admin screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTree(): array
    {
        $byParent = OrgUnit::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->groupBy(fn (OrgUnit $u) => $u->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, collect())->map(fn (OrgUnit $u) => [
                'id' => $u->id,
                'type' => $u->type->value,
                'type_label' => $u->type->label(),
                'name' => $u->name,
                'code' => $u->code,
                'is_active' => (bool) $u->is_active,
                'children' => $build($u->id),
            ])->values()->all();
        };

        return $build(0);
    }

    public function getForEditShow($id): array
    {
        return $this->toRow(OrgUnit::findOrFail($id));
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $this->assertValidNesting($data);

            $unit = OrgUnit::create($data);
            $this->fileUploads->processUploads($unit, $data, ['logo' => 'logo_path'], 'org-units', $unit->name);

            if ($unit->default_work_schedule_id) {
                $this->scheduleAssignments->seedUnitMembers($unit);
            }

            activity()->performedOn($unit)->log('Org unit created successfully #'.($unit->id ?? null));

            return $unit;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $unit = OrgUnit::findOrFail($id);

            $this->assertValidNesting($data, (int) $unit->id);

            $originalDefault = $unit->default_work_schedule_id;

            $unit->update($data);
            $this->fileUploads->processUploads($unit, $data, ['logo' => 'logo_path'], 'org-units', $unit->name);

            // Newly set/changed default → seed members who have no active schedule yet.
            if ($unit->default_work_schedule_id && $unit->default_work_schedule_id !== $originalDefault) {
                $this->scheduleAssignments->seedUnitMembers($unit);
            }

            activity()->performedOn($unit)->log('Org unit updated successfully #'.($unit->id ?? null));

            return $unit;
        });
    }

    public function delete($id)
    {
        $unit = OrgUnit::find($id);

        if (! $unit) {
            return false;
        }

        if ($unit->children()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['Remove or move the child units before deleting this unit.'],
            ]);
        }

        $unit->delete();

        activity()->performedOn($unit)->log('Org unit deleted successfully #'.($unit->id ?? null));

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValidNesting(array $data, ?int $selfId = null): void
    {
        $type = OrgUnitType::from($data['type']);
        $parent = isset($data['parent_id']) && $data['parent_id'] !== null
            ? OrgUnit::find($data['parent_id'])
            : null;

        if (! $type->canHaveParent($parent?->type)) {
            $under = $parent ? $parent->type->label() : 'the root';
            throw ValidationException::withMessages([
                'parent_id' => ["A {$type->label()} cannot be placed under {$under}."],
            ]);
        }

        if ($selfId !== null && $parent !== null && in_array($parent->id, OrgUnit::subtreeIds($selfId), true)) {
            throw ValidationException::withMessages([
                'parent_id' => ['Cannot move a unit under itself or one of its descendants.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(OrgUnit $u): array
    {
        return [
            'id' => $u->id,
            'parent_id' => $u->parent_id,
            'parent_name' => $u->parent?->name,
            'type' => $u->type->value,
            'type_label' => $u->type->label(),
            'name' => $u->name,
            'code' => $u->code,
            'logo_url' => $u->logoUrl(),
            'default_work_schedule_id' => $u->default_work_schedule_id,
            'sort_order' => $u->sort_order,
            'address' => $u->address,
            'phone' => $u->phone,
            'email' => $u->email,
            'latitude' => $u->latitude,
            'longitude' => $u->longitude,
            'geofence_radius_meters' => $u->geofence_radius_meters,
            'has_location' => (bool) $u->has_location,
            'is_active' => (bool) $u->is_active,
        ];
    }
}
