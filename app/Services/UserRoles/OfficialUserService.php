<?php

namespace App\Services\UserRoles;

use App\Models\Official;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class OfficialUserService
{
    /**
     * Officials list for the master data table.
     */
    public function getForDataTable()
    {
        return User::query()
            ->whereDoesntHave('ghostUser')
            ->role('official')
            ->with('roles', 'official')
            ->get()
            ->map(function ($user) {
                $user->role = 'official';
                $user->contact = $user->contact ?? '';
                $user->email = $this->displayEmail($user->email);

                if ($user->official) {
                    $user->type = $user->official->type ?? '';
                    $user->nationality = $user->official->nationality ?? '';
                    $user->passport_number = $user->official->passport_number ?? '';
                    $user->passport_issue_date = $user->official->passport_issue_date_formatted ?? '';
                    $user->passport_expiry_date = $user->official->passport_expiry_date_formatted ?? '';
                    $user->passport_place_of_issue = $user->official->passport_place_of_issue ?? '';
                    $user->gender = $user->official->gender ?? '';
                    $user->date_of_birth = $user->official->date_of_birth_formatted ?? '';
                    $user->place_of_birth = $user->official->place_of_birth ?? '';
                }

                return $user;
            });
    }

    /**
     * Flat list used to populate the official select on package & proposal forms.
     *
     * Soft-deleted officials are excluded from new selections. Officials already
     * snapshotted onto a package/proposal stay visible there via the stored
     * snapshot (the form falls back to it when the master is absent), so dropping
     * them here does not hide them from records that already reference them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForSelect(): array
    {
        return Official::query()
            // user() is a withTrashed relation; re-exclude trashed users explicitly.
            ->whereHas('user', fn ($query) => $query->whereNull('deleted_at'))
            ->with('user')
            ->get()
            ->map(fn (Official $official): array => [
                'id' => $official->id,
                ...$this->officialToSnapshot($official),
            ])
            ->values()
            ->all();
    }

    /**
     * Authoritative snapshot for a master official, sourced server-side so the
     * package/proposal copy cannot drift from (or be tampered against) the master.
     * Loads soft-deleted-user officials too, so rows already linked to a deleted
     * official still reconcile to their (unchanged) master data.
     *
     * @return array<string, mixed>|null
     */
    public function findSnapshot(int $officialId): ?array
    {
        $official = Official::query()->with('user')->find($officialId);

        return $official ? $this->officialToSnapshot($official) : null;
    }

    /**
     * Canonical official snapshot shared by getForSelect() and findSnapshot().
     *
     * @return array<string, mixed>
     */
    private function officialToSnapshot(Official $official): array
    {
        return [
            'type' => $official->type,
            'name' => $official->user->name ?? '',
            'contact_number' => $official->user->contact ?? '',
            'nationality' => $official->nationality,
            'passport_number' => $official->passport_number,
            'gender' => $official->gender,
            'date_of_birth' => $official->date_of_birth_formatted,
            'place_of_birth' => $official->place_of_birth,
            'passport_issue_date' => $official->passport_issue_date_formatted,
            'passport_expiry_date' => $official->passport_expiry_date_formatted,
            'passport_place_of_issue' => $official->passport_place_of_issue,
        ];
    }

    public function store(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->createOrRestoreUser($data);

            $user->syncRoles([Role::findByName('official')]);

            Official::updateOrCreate([
                'user_id' => $user->id,
            ], $this->officialAttributes($data));

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'OfficialUser', 'subject_id' => $user->id ?? null])
                ->log('OfficialUser created successfully #'.($user->id ?? null));

            return $user;
        });
    }

    public function update(array $data, $id): User
    {
        return DB::transaction(function () use ($data, $id) {
            $user = User::role('official')->with('official')->findOrFail($id);

            // Keep the existing (often auto-generated) email when none is submitted,
            // so editing an official does not churn a fresh placeholder each save.
            $email = trim((string) ($data['email'] ?? ''));

            $user->update([
                'name' => $data['name'],
                'email' => $email !== '' ? $email : $user->email,
                'contact' => $data['contact'] ?? null,
            ]);

            Official::updateOrCreate([
                'user_id' => $user->id,
            ], $this->officialAttributes($data));

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'OfficialUser', 'subject_id' => $user->id ?? null])
                ->log('OfficialUser updated successfully #'.($user->id ?? null));

            return $user;
        });
    }

    public function getForEditShow($id): array
    {
        $user = User::role('official')->with('official')->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $this->displayEmail($user->email),
            'contact' => $user->contact ?? '',
            'role' => 'official',
            'type' => $user->official->type ?? '',
            'nationality' => $user->official->nationality ?? '',
            'passport_number' => $user->official->passport_number ?? '',
            'passport_issue_date' => $user->official->passport_issue_date_formatted ?? '',
            'passport_expiry_date' => $user->official->passport_expiry_date_formatted ?? '',
            'passport_place_of_issue' => $user->official->passport_place_of_issue ?? '',
            'gender' => $user->official->gender ?? '',
            'date_of_birth' => $user->official->date_of_birth_formatted ?? '',
            'place_of_birth' => $user->official->place_of_birth ?? '',
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function officialAttributes(array $data): array
    {
        return [
            'type' => $data['type'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'passport_issue_date' => $data['passport_issue_date'] ?? null,
            'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
            'passport_place_of_issue' => $data['passport_place_of_issue'] ?? null,
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'place_of_birth' => $data['place_of_birth'] ?? null,
        ];
    }

    private function createOrRestoreUser(array $data): User
    {
        $email = $this->resolveEmail($data['email'] ?? null);

        $existingUser = User::withTrashed()->where('email', $email)->first();

        if ($existingUser && $existingUser->trashed()) {
            $existingUser->restore();
            $existingUser->update([
                'name' => $data['name'],
                'email' => $email,
                'contact' => $data['contact'] ?? null,
            ]);

            return $existingUser->fresh();
        }

        return User::create([
            'name' => $data['name'],
            'email' => $email,
            'contact' => $data['contact'] ?? null,
            // Officials cannot log in; a non-guessable password is set as a placeholder.
            'password' => Hash::make(Str::random(40)),
        ]);
    }

    private function resolveEmail(?string $email): string
    {
        $email = trim((string) $email);

        return $email !== '' ? $email : 'official+'.Str::lower(Str::random(12)).'@noemail.local';
    }

    /**
     * Hide the auto-generated placeholder email from the UI (officials cannot log in).
     */
    private function displayEmail(?string $email): string
    {
        $email = (string) $email;

        return str_ends_with($email, '@noemail.local') ? '' : $email;
    }
}
