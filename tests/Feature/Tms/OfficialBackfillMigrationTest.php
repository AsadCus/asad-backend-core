<?php

namespace Tests\Feature\Tms;

use App\Models\Official;
use App\Models\Package;
use App\Models\PackageOfficial;
use App\Models\PackageProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class OfficialBackfillMigrationTest extends TestCase
{
    private function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2026_06_16_024750_backfill_officials_from_packages_and_proposals.php',
        );
        $migration->up();
    }

    public function test_backfill_links_and_dedupes_package_and_proposal_officials(): void
    {
        Role::findOrCreate('official', 'web');

        $package = Package::create(['name' => 'Legacy Package', 'status' => 'open']);

        // Package official with passport (dedupe key shared with a proposal official).
        $pkgAhmad = PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'mutawif',
            'name' => 'Ahmad',
            'passport_number' => 'P100',
            'nationality' => 'Malaysian',
        ]);

        // Package official without passport (dedupe by name+contact).
        $pkgBudi = PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'official',
            'name' => 'Budi',
            'contact_number' => '0123',
        ]);

        $proposal = PackageProposal::create([
            'name' => 'Legacy Proposal',
            'officials' => [
                ['type' => 'mutawif', 'name' => 'Ahmad', 'passport_number' => 'P100'],
                ['type' => 'official', 'name' => 'Citra'],
            ],
        ]);

        $this->runBackfill();

        // Distinct people: Ahmad, Budi, Citra => 3 masters + 3 official users.
        $this->assertSame(3, Official::count());
        $this->assertSame(3, User::role('official')->count());

        $pkgAhmad->refresh();
        $pkgBudi->refresh();
        $proposal->refresh();

        $this->assertNotNull($pkgAhmad->official_id);
        $this->assertNotNull($pkgBudi->official_id);

        $proposalOfficials = $proposal->officials;
        $this->assertNotEmpty($proposalOfficials[0]['official_id']);
        $this->assertNotEmpty($proposalOfficials[1]['official_id']);

        // Ahmad (same passport) is shared between the package and the proposal.
        $this->assertSame(
            (int) $pkgAhmad->official_id,
            (int) $proposalOfficials[0]['official_id'],
        );

        // Master carries snapshot details + the linked user keeps the name.
        $ahmad = Official::with('user')->find($pkgAhmad->official_id);
        $this->assertSame('Ahmad', $ahmad->user->name);
        $this->assertSame('P100', $ahmad->passport_number);
        $this->assertSame('mutawif', $ahmad->type);
    }

    public function test_backfill_is_idempotent(): void
    {
        Role::findOrCreate('official', 'web');

        $package = Package::create(['name' => 'Idempotent Package', 'status' => 'open']);
        PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'mutawif',
            'name' => 'Dewi',
            'passport_number' => 'P200',
        ]);

        $this->runBackfill();
        $countAfterFirst = Official::count();

        $this->runBackfill();

        $this->assertSame($countAfterFirst, Official::count());
    }

    public function test_backfill_skips_officials_without_name(): void
    {
        Role::findOrCreate('official', 'web');

        $package = Package::create(['name' => 'Nameless Package', 'status' => 'open']);
        PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'official',
            'name' => '',
        ]);

        $this->runBackfill();

        $this->assertSame(0, Official::count());
    }

    public function test_backfill_does_not_merge_same_name_without_passport_or_contact(): void
    {
        Role::findOrCreate('official', 'web');

        $package = Package::create(['name' => 'Ambiguous Package', 'status' => 'open']);

        // Two different people sharing a name, with no passport and no contact:
        // there is no reliable identity, so they must NOT be merged.
        PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'official',
            'name' => 'Ali',
        ]);
        PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'official',
            'name' => 'Ali',
        ]);

        $this->runBackfill();

        $this->assertSame(2, Official::count());
    }
}
