<?php

namespace Tests\Feature\Tms;

use App\Models\Official;
use App\Models\User;
use App\Services\UserRoles\OfficialUserService;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class OfficialControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('official', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $this->actingAs($admin);
    }

    public function test_store_creates_official_and_redirects(): void
    {
        $response = $this->post(route('master.user.official.store'), [
            'name' => 'New Official',
            'email' => 'new@example.com',
            'contact' => '0123456789',
            'role' => 'official',
            'type' => 'mutawif',
            'passport_number' => 'A1234567',
        ]);

        $response->assertRedirect(route('master.user.official.index'));
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('official'));
        $this->assertDatabaseHas('officials', ['user_id' => $user->id, 'type' => 'mutawif']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $response = $this->post(route('master.user.official.store'), [
            'name' => 'Bad Type',
            'role' => 'official',
            'type' => 'not-a-real-type',
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertSame(0, Official::count());
    }

    public function test_store_requires_type(): void
    {
        $response = $this->post(route('master.user.official.store'), [
            'name' => 'No Type',
            'role' => 'official',
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertSame(0, Official::count());
    }

    public function test_update_changes_official_details(): void
    {
        $user = app(OfficialUserService::class)->store([
            'name' => 'Before',
            'email' => 'before@example.com',
            'type' => 'mutawif',
        ]);

        $response = $this->put(route('master.user.official.update', $user->id), [
            'name' => 'After',
            'email' => 'before@example.com',
            'role' => 'official',
            'type' => 'official',
        ]);

        $response->assertRedirect(route('master.user.official.index'));
        $this->assertSame('After', $user->fresh()->name);
        $this->assertDatabaseHas('officials', ['user_id' => $user->id, 'type' => 'official']);
    }

    public function test_destroy_single_soft_deletes_user(): void
    {
        $user = app(OfficialUserService::class)->store([
            'name' => 'Delete Me',
            'type' => 'official',
        ]);

        $response = $this->delete(route('master.user.official.destroy', $user->id));

        $response->assertRedirect(route('master.user.official.index'));
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_destroy_bulk_soft_deletes_all_ids(): void
    {
        $service = app(OfficialUserService::class);
        $a = $service->store(['name' => 'A', 'type' => 'official']);
        $b = $service->store(['name' => 'B', 'type' => 'official']);

        $response = $this->delete(route('master.user.official.destroy', $a->id), [
            'ids' => [$a->id, $b->id],
        ]);

        $response->assertRedirect(route('master.user.official.index'));
        $this->assertSoftDeleted('users', ['id' => $a->id]);
        $this->assertSoftDeleted('users', ['id' => $b->id]);
    }
}
