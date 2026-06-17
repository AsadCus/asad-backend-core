<?php

namespace Tests\Feature\Tms;

use App\Models\Official;
use App\Models\Package;
use App\Models\PackageOfficial;
use App\Services\UserRoles\OfficialUserService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class OfficialUserServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('official', 'web');
    }

    public function test_store_creates_official_user_with_role_and_details(): void
    {
        $user = app(OfficialUserService::class)->store([
            'name' => 'Ustaz Mutawif',
            'email' => 'mutawif@example.com',
            'contact' => '0123456789',
            'type' => 'mutawif',
            'passport_number' => 'A1234567',
            'nationality' => 'Malaysian',
        ]);

        $this->assertTrue($user->hasRole('official'));

        $official = Official::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('mutawif', $official->type);
        $this->assertSame('A1234567', $official->passport_number);
        $this->assertSame('Malaysian', $official->nationality);
    }

    public function test_store_auto_generates_email_when_blank(): void
    {
        $user = app(OfficialUserService::class)->store([
            'name' => 'No Email Official',
            'type' => 'official',
        ]);

        $this->assertNotEmpty($user->email);
        $this->assertStringEndsWith('@noemail.local', $user->email);
    }

    public function test_official_cannot_login_with_known_default_password(): void
    {
        $user = app(OfficialUserService::class)->store([
            'name' => 'Locked Official',
            'type' => 'official',
        ]);

        // The customer flow uses 'password' as the default; officials must NOT.
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_get_for_select_returns_flat_official_options(): void
    {
        $service = app(OfficialUserService::class);
        $user = $service->store([
            'name' => 'Selectable Official',
            'email' => 'selectable@example.com',
            'type' => 'mutawifah',
        ]);

        $options = $service->getForSelect();

        $this->assertCount(1, $options);
        $this->assertSame('Selectable Official', $options[0]['name']);
        $this->assertSame('mutawifah', $options[0]['type']);
        $this->assertSame(
            Official::where('user_id', $user->id)->value('id'),
            $options[0]['id'],
        );
    }

    public function test_get_for_select_excludes_soft_deleted_officials(): void
    {
        $service = app(OfficialUserService::class);
        $user = $service->store([
            'name' => 'Soon Deleted Official',
            'email' => 'deleted@example.com',
            'type' => 'official',
        ]);

        $this->assertCount(1, $service->getForSelect());

        $user->delete();

        $this->assertCount(0, $service->getForSelect());
    }

    public function test_already_selected_official_stays_visible_after_soft_delete(): void
    {
        $service = app(OfficialUserService::class);
        $user = $service->store([
            'name' => 'Snapshotted Official',
            'email' => 'snapshot@example.com',
            'type' => 'mutawif',
            'passport_number' => 'P900',
        ]);
        $officialId = Official::where('user_id', $user->id)->value('id');

        // Simulate a package that already selected this official (snapshot is stored
        // on the package_officials row, independent of the master record).
        $package = Package::create(['name' => 'Has Official', 'status' => 'open']);
        $packageOfficial = PackageOfficial::create([
            'package_id' => $package->id,
            'official_id' => $officialId,
            'type' => 'mutawif',
            'name' => 'Snapshotted Official',
            'passport_number' => 'P900',
        ]);

        $user->delete();

        // Excluded from new selections...
        $this->assertCount(0, $service->getForSelect());

        // ...but the stored snapshot on the existing package is untouched.
        $packageOfficial->refresh();
        $this->assertSame((int) $officialId, (int) $packageOfficial->official_id);
        $this->assertSame('Snapshotted Official', $packageOfficial->name);
        $this->assertSame('P900', $packageOfficial->passport_number);
    }

    public function test_update_keeps_existing_email_when_blank(): void
    {
        $service = app(OfficialUserService::class);
        $user = $service->store([
            'name' => 'Churn Test',
            'type' => 'official',
        ]);
        $originalEmail = $user->email;

        $service->update([
            'name' => 'Churn Test Renamed',
            'email' => '',
            'type' => 'official',
        ], $user->id);

        $this->assertSame($originalEmail, $user->fresh()->email);
    }

    public function test_get_for_edit_show_blanks_placeholder_email(): void
    {
        $service = app(OfficialUserService::class);
        $user = $service->store([
            'name' => 'Placeholder Email',
            'type' => 'official',
        ]);

        $this->assertStringEndsWith('@noemail.local', $user->email);
        $this->assertSame('', $service->getForEditShow($user->id)['email']);
    }
}
