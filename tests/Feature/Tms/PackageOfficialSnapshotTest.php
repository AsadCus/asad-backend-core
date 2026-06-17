<?php

namespace Tests\Feature\Tms;

use App\Enums\PackageProposalStatus;
use App\Models\Official;
use App\Models\PackageProposal;
use App\Rules\PackageRule;
use App\Services\PackageProposalService;
use App\Services\PackageService;
use App\Services\UserRoles\OfficialUserService;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class PackageOfficialSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('official', 'web');
    }

    private function createMasterOfficial(): Official
    {
        $user = app(OfficialUserService::class)->store([
            'name' => 'Master Official',
            'email' => 'master@example.com',
            'contact' => '0123456789',
            'type' => 'mutawif',
            'nationality' => 'Malaysian',
            'passport_number' => 'MASTER-PP',
        ]);

        return Official::where('user_id', $user->id)->firstOrFail();
    }

    public function test_package_snapshot_is_taken_from_master_not_client(): void
    {
        $official = $this->createMasterOfficial();

        $package = app(PackageService::class)->store([
            'name' => 'Reconcile Package',
            'status' => 'open',
            'total_seats' => 5,
            'officials' => [
                [
                    'official_id' => $official->id,
                    'type' => 'mutawif',
                    // Tampered client values that must be ignored in favour of the master.
                    'name' => 'TAMPERED NAME',
                    'contact_number' => '9999999999',
                    'passport_number' => 'HACKED-PP',
                    'nationality' => 'Faked',
                ],
            ],
        ]);

        $row = $package->officials()->first();

        $this->assertSame((int) $official->id, (int) $row->official_id);
        $this->assertSame('Master Official', $row->name);
        $this->assertSame('MASTER-PP', $row->passport_number);
        $this->assertSame('0123456789', $row->contact_number);
        $this->assertSame('Malaysian', $row->nationality);
    }

    public function test_legacy_official_without_master_keeps_client_values(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Legacy Package',
            'status' => 'open',
            'total_seats' => 5,
            'officials' => [
                [
                    'official_id' => null,
                    'type' => 'official',
                    'name' => 'Free Typed',
                    'passport_number' => 'FREE-PP',
                ],
            ],
        ]);

        $row = $package->officials()->first();

        $this->assertNull($row->official_id);
        $this->assertSame('Free Typed', $row->name);
        $this->assertSame('FREE-PP', $row->passport_number);
    }

    public function test_package_rule_rejects_nonexistent_official_id(): void
    {
        $validator = Validator::make(
            ['name' => 'P', 'officials' => [['official_id' => 999999, 'name' => 'X']]],
            (new PackageRule)->rules(),
        );

        $this->assertTrue($validator->errors()->has('officials.0.official_id'));
    }

    public function test_proposal_conversion_reconciles_snapshot_from_master(): void
    {
        $official = $this->createMasterOfficial();

        $proposal = PackageProposal::create([
            'name' => 'Convertible Proposal',
            'status' => PackageProposalStatus::Approved,
            'officials' => [
                [
                    'official_id' => $official->id,
                    'type' => 'mutawif',
                    'name' => 'TAMPERED NAME',
                    'passport_number' => 'HACKED-PP',
                ],
            ],
        ]);

        $package = app(PackageProposalService::class)->createPackageFromProposal($proposal->id);

        $row = $package->officials()->first();

        $this->assertSame((int) $official->id, (int) $row->official_id);
        $this->assertSame('Master Official', $row->name);
        $this->assertSame('MASTER-PP', $row->passport_number);
    }

    public function test_proposal_normalize_reconciles_snapshot_from_master(): void
    {
        $official = $this->createMasterOfficial();

        $proposal = app(PackageProposalService::class)->store([
            'name' => 'Snapshot Proposal',
            'officials' => [
                [
                    'official_id' => $official->id,
                    'type' => 'mutawif',
                    'name' => 'TAMPERED NAME',
                    'passport_number' => 'HACKED-PP',
                ],
            ],
        ]);

        $stored = $proposal->fresh()->officials[0];

        $this->assertSame((int) $official->id, (int) $stored['official_id']);
        $this->assertSame('Master Official', $stored['name']);
        $this->assertSame('MASTER-PP', $stored['passport_number']);
    }
}
