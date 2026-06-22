<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                'is_system' => false,
                'is_full_access' => $this->resolveFullAccess($data),
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

            // Only a ghost may edit a full-access role (so an admin can't tamper with the top tier).
            if ($role->is_full_access && ! $this->actorIsGhost()) {
                throw ValidationException::withMessages([
                    'is_full_access' => ['Only a ghost user may edit a full-access role.'],
                ]);
            }

            // Machine name stays immutable; only label/metadata/permissions change.
            $role->update([
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'role_group_id' => $data['role_group_id'] ?? null,
                'management_level_id' => $data['management_level_id'] ?? null,
                'is_full_access' => $this->resolveFullAccess($data, $role),
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

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'id' => ['System roles cannot be deleted.'],
            ]);
        }

        $role->delete();

        activity()->performedOn($role)->log('Role deleted successfully #'.($role->id ?? null));

        return true;
    }

    private function resolveFullAccess(array $data, ?Role $existing = null): bool
    {
        $requested = (bool) ($data['is_full_access'] ?? ($existing?->is_full_access ?? false));

        // Full-access can only be granted/kept by a ghost; non-ghosts can't escalate.
        return $requested && $this->actorIsGhost();
    }

    private function actorIsGhost(): bool
    {
        $user = auth()->user();

        return $user !== null && method_exists($user, 'isGhostUser') && $user->isGhostUser();
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
            'is_system' => (bool) $role->is_system,
            'is_full_access' => (bool) $role->is_full_access,
            'permissions_count' => $role->permissions_count ?? $role->permissions()->count(),
            'users_count' => $role->users()->count(),
        ];
    }
}
