<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class RoleService
{
    public function getForDataTable()
    {
        return Role::query()
            ->with(['roleGroup', 'managementLevel'])
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->toRow($role));
    }

    public function getForFilter()
    {
        return Role::query()->orderBy('name')->get()->map(fn (Role $role) => [
            'value' => $role->name,
            'label' => $role->label ?: Str::headline($role->name),
        ]);
    }

    /**
     * All permissions grouped by their domain prefix (for the permission-matrix editor).
     *
     * @return array<int, array{group: string, permissions: array<int, array{name: string, label: string}>}>
     */
    public function permissionGroups(): array
    {
        return Permission::query()->orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => Str::of($name)->before(' ')->replace('hris.', '')->headline()->toString())
            ->map(fn ($names, $group) => [
                'group' => $group,
                'permissions' => $names->map(fn (string $name) => [
                    'name' => $name,
                    'label' => Str::of($name)->after(' ')->headline()->toString() ?: $name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Every role with its explicit permission names — drives the frontend "preview as role"
     * switcher (pick a role, see the nav its permission set produces).
     *
     * @return array<int, array{id: int, name: string, label: string, permissions: array<int, string>}>
     */
    public function permissionSets(): array
    {
        return Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?: Str::headline($role->name),
                'permissions' => $role->permissions->pluck('name')->all(),
            ])
            ->all();
    }

    public function getForEditShow($id): array
    {
        $role = Role::with(['roleGroup', 'managementLevel'])->findOrFail($id);

        return $this->toRow($role) + [
            'permissions' => $role->permissions->pluck('name')->all(),
        ];
    }

    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $name = $this->uniqueName($data['label']);

            $role = Role::create([
                'name' => $name,
                'guard_name' => 'web',
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'role_group_id' => $data['role_group_id'] ?? null,
                'management_level_id' => $data['management_level_id'] ?? null,
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            activity()->performedOn($role)->log('Role created successfully #'.($role->id ?? null));

            return $role;
        });
    }

    public function update(array $data, $id): Role
    {
        return DB::transaction(function () use ($data, $id) {
            $role = Role::findOrFail($id);

            // Machine name stays immutable; only label/metadata/permissions change.
            $role->update([
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'role_group_id' => $data['role_group_id'] ?? null,
                'management_level_id' => $data['management_level_id'] ?? null,
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            activity()->performedOn($role)->log('Role updated successfully #'.($role->id ?? null));

            return $role;
        });
    }

    public function delete($id): bool
    {
        $role = Role::find($id);

        if (! $role) {
            return false;
        }

        $role->delete();

        activity()->performedOn($role)->log('Role deleted successfully #'.($role->id ?? null));

        return true;
    }

    private function uniqueName(string $label): string
    {
        $base = Str::slug($label) ?: 'role';
        $name = $base;
        $i = 2;

        while (Role::where('name', $name)->exists()) {
            $name = $base.'-'.$i;
            $i++;
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label ?: Str::headline($role->name),
            'description' => $role->description,
            'role_group_id' => $role->role_group_id,
            'role_group' => $role->roleGroup ? [
                'name' => $role->roleGroup->name,
                'color' => $role->roleGroup->color,
            ] : null,
            'management_level_id' => $role->management_level_id,
            'management_level' => $role->managementLevel ? [
                'name' => $role->managementLevel->name,
                'color' => $role->managementLevel->color,
            ] : null,
            'permissions_count' => $role->permissions_count ?? $role->permissions()->count(),
            'users_count' => $role->users()->count(),
        ];
    }
}
