<?php

use App\Models\Official;
use App\Models\PackageOfficial;
use App\Models\PackageProposal;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Dedupe registry: dedupe key => official id.
     *
     * @var array<string, int>
     */
    private array $registry = [];

    public function up(): void
    {
        DB::transaction(function (): void {
            Role::findOrCreate('official', 'web');

            // Seed registry from officials already created via the UI, so we link
            // existing package/PNL officials to them instead of duplicating.
            Official::with('user')->get()->each(function (Official $official): void {
                $key = $this->dedupeKey(
                    $official->passport_number,
                    $official->user->name ?? null,
                    $official->user->contact ?? null,
                );

                if ($key !== null && ! isset($this->registry[$key])) {
                    $this->registry[$key] = (int) $official->id;
                }
            });

            $this->backfillPackageOfficials();
            $this->backfillProposalOfficials();
        });
    }

    public function down(): void
    {
        // Data migration: created users/officials are intentionally not removed.
    }

    private function backfillPackageOfficials(): void
    {
        PackageOfficial::whereNull('official_id')->get()->each(function (PackageOfficial $row): void {
            if (trim((string) $row->name) === '') {
                return;
            }

            $row->official_id = $this->findOrCreateOfficial([
                'name' => $row->name,
                'contact_number' => $row->contact_number,
                'type' => $row->type,
                'nationality' => $row->nationality,
                'passport_number' => $row->passport_number,
                'passport_issue_date' => $row->passport_issue_date,
                'passport_expiry_date' => $row->passport_expiry_date,
                'passport_place_of_issue' => $row->passport_place_of_issue,
                'gender' => $row->gender,
                'date_of_birth' => $row->date_of_birth,
                'place_of_birth' => $row->place_of_birth,
            ]);
            $row->save();
        });
    }

    private function backfillProposalOfficials(): void
    {
        PackageProposal::all()->each(function (PackageProposal $proposal): void {
            $officials = $proposal->officials ?? [];

            if (! is_array($officials) || $officials === []) {
                return;
            }

            $changed = false;

            foreach ($officials as $index => $official) {
                if (! is_array($official)) {
                    continue;
                }

                if (trim((string) ($official['name'] ?? '')) === '') {
                    continue;
                }

                if (! empty($official['official_id'])) {
                    continue;
                }

                $officials[$index]['official_id'] = $this->findOrCreateOfficial([
                    'name' => $official['name'] ?? null,
                    'contact_number' => $official['contact_number'] ?? null,
                    'type' => $official['type'] ?? null,
                    'nationality' => $official['nationality'] ?? null,
                    'passport_number' => $official['passport_number'] ?? null,
                    'passport_issue_date' => $official['passport_issue_date'] ?? null,
                    'passport_expiry_date' => $official['passport_expiry_date'] ?? null,
                    'passport_place_of_issue' => $official['passport_place_of_issue'] ?? null,
                    'gender' => $official['gender'] ?? null,
                    'date_of_birth' => $official['date_of_birth'] ?? null,
                    'place_of_birth' => $official['place_of_birth'] ?? null,
                ]);
                $changed = true;
            }

            if ($changed) {
                $proposal->officials = $officials;
                $proposal->save();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function findOrCreateOfficial(array $attrs): int
    {
        $key = $this->dedupeKey(
            $attrs['passport_number'] ?? null,
            $attrs['name'] ?? null,
            $attrs['contact_number'] ?? null,
        );

        if ($key !== null && isset($this->registry[$key])) {
            return $this->registry[$key];
        }

        $user = User::create([
            'name' => trim((string) $attrs['name']),
            'email' => 'official+'.Str::lower(Str::random(12)).'@noemail.local',
            'contact' => $attrs['contact_number'] ?? null,
            // Officials cannot log in; placeholder password mirrors OfficialUserService.
            'password' => Hash::make(Str::random(40)),
        ]);

        $user->assignRole('official');

        $official = Official::create([
            'user_id' => $user->id,
            'type' => $attrs['type'] ?? null,
            'nationality' => $attrs['nationality'] ?? null,
            'passport_number' => $attrs['passport_number'] ?? null,
            'passport_issue_date' => $attrs['passport_issue_date'] ?? null,
            'passport_expiry_date' => $attrs['passport_expiry_date'] ?? null,
            'passport_place_of_issue' => $attrs['passport_place_of_issue'] ?? null,
            'gender' => $attrs['gender'] ?? null,
            'date_of_birth' => $attrs['date_of_birth'] ?? null,
            'place_of_birth' => $attrs['place_of_birth'] ?? null,
        ]);

        if ($key !== null) {
            $this->registry[$key] = (int) $official->id;
        }

        return (int) $official->id;
    }

    private function dedupeKey(mixed $passport, mixed $name, mixed $contact): ?string
    {
        $passport = strtolower(trim((string) $passport));

        if ($passport !== '') {
            return 'p:'.$passport;
        }

        $name = strtolower(trim((string) $name));
        $contact = strtolower(trim((string) $contact));

        // Only dedupe by name+contact when BOTH are present. Without a passport or a
        // contact there is no reliable identity, so treat each row as a distinct
        // person (over-create is safer than merging different people by name alone).
        if ($name === '' || $contact === '') {
            return null;
        }

        return 'nc:'.$name.'|'.$contact;
    }
};
