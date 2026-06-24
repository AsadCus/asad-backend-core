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
            ->with(['roles'])
            ->orderBy('name');

        if ($role) {
            $query->role($role);
        }

        return $query->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'role' => $user->roles->first()?->name,
            'role_label' => $user->roles->first()?->label ?? $user->roles->first()?->name,
        ]);
    }

    public function getForEditShow($id): array
    {
        $user = User::query()->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'role' => $user->getRoleNames()->first(),
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

    /**
     * Create/restore (when $userId is null) or update the login account behind an employee and
     * sync its role. Account-only — the Employee profile is owned by EmployeeService. Accepts the
     * employee form's `phone` as the user `contact`.
     *
     * @param  array<string, mixed>  $data
     */
    public function provisionAccount(array $data, ?int $userId = null): User
    {
        return DB::transaction(function () use ($data, $userId) {
            if ($userId) {
                $user = User::findOrFail($userId);
                $user->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'contact' => $data['phone'] ?? $data['contact'] ?? $user->contact,
                ]);
                if (! empty($data['password'])) {
                    $user->update(['password' => Hash::make((string) $data['password'])]);
                }
            } else {
                $user = $this->createOrRestoreUser([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'contact' => $data['phone'] ?? $data['contact'] ?? null,
                    'password' => $data['password'] ?? null,
                ]);
            }

            if (! empty($data['role'])) {
                $user->syncRoles([Role::findByName($data['role'], 'web')]);
            }

            return $user;
        });
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
                'employee_no' => $employee?->employee_no ?? sprintf('EMP-%04d', $user->id),
                'org_unit_id' => $data['org_unit_id'] ?? $employee?->org_unit_id,
                'hire_date' => $employee?->hire_date ?? now()->toDateString(),
                'employment_status' => $employee?->employment_status?->value ?? EmploymentStatus::Permanent->value,
                'is_active' => true,
            ],
        );
    }
}
