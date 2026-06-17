<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * HRIS user management: every user is User + role + a linked Employee carrying the position.
 * Ghost users are hidden from the management lists.
 */
class HrisUserService
{
    public function getForDataTable(?string $role = null)
    {
        $query = User::query()
            ->whereDoesntHave('ghostUser')
            ->with(['employee.position', 'roles'])
            ->orderBy('name');

        if ($role) {
            $query->role($role);
        }

        return $query->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'role' => $user->getRoleNames()->first(),
            'position_id' => $user->employee?->position_id,
            'position_name' => $user->employee?->position?->name,
        ]);
    }

    public function getForEditShow($id): array
    {
        $user = User::query()->with('employee')->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'role' => $user->getRoleNames()->first(),
            'position_id' => $user->employee?->position_id,
        ];
    }

    public function countByRole(string $role): int
    {
        return User::query()->whereDoesntHave('ghostUser')->role($role)->count();
    }

    public function store(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->createOrRestoreUser($data);
            $user->syncRoles([Role::findByName($data['role'], 'web')]);
            $this->syncEmployee($user, $data);

            activity()->performedOn($user)->log('User created successfully #'.($user->id ?? null));

            return $user;
        });
    }

    public function update(array $data, $id): User
    {
        return DB::transaction(function () use ($data, $id) {
            $user = User::findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
            ]);

            if (! empty($data['password'])) {
                $user->update(['password' => Hash::make((string) $data['password'])]);
            }

            $user->syncRoles([Role::findByName($data['role'], 'web')]);
            $this->syncEmployee($user, $data);

            activity()->performedOn($user)->log('User updated successfully #'.($user->id ?? null));

            return $user;
        });
    }

    public function delete($id): bool
    {
        $user = User::find($id);

        if (! $user) {
            return false;
        }

        $user->delete();

        activity()->performedOn($user)->log('User deleted successfully #'.($user->id ?? null));

        return true;
    }

    private function createOrRestoreUser(array $data): User
    {
        $password = ! empty($data['password'])
            ? Hash::make((string) $data['password'])
            : Hash::make('password');

        $existing = User::withTrashed()->where('email', (string) $data['email'])->first();

        if ($existing && $existing->trashed()) {
            $existing->restore();
            $existing->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => $password,
            ]);

            return $existing->fresh();
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'contact' => $data['contact'] ?? null,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }

    private function syncEmployee(User $user, array $data): void
    {
        $employee = Employee::withTrashed()->where('user_id', $user->id)->first();

        if ($employee && $employee->trashed()) {
            $employee->restore();
        }

        Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'position_id' => $data['position_id'] ?? null,
                'employee_no' => $employee?->employee_no ?? sprintf('EMP-%04d', $user->id),
                'hire_date' => $employee?->hire_date ?? now()->toDateString(),
                'employment_status' => $employee?->employment_status?->value ?? EmploymentStatus::Permanent->value,
                'is_active' => true,
            ],
        );
    }
}
