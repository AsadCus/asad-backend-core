<?php

namespace Tests\Feature;

use App\Models\MenuOverride;
use App\Models\MenuUserPreference;
use App\Models\User;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\MenuOverrideSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuConfigApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_config_includes_role_default_favorites(): void
    {
        $this->actingAs($this->makeUser('employee'), 'sanctum');

        $this->getJson('/api/menu/config')
            ->assertOk()
            ->assertJsonPath('roleDefaultFavorites', config('menu.default_favorites.employee'));
    }

    public function test_admin_can_save_overrides(): void
    {
        $this->actingAs($this->makeUser('administrator'), 'sanctum');

        $this->putJson('/api/menu/overrides', [
            'overrides' => [
                ['menu_key' => 'nav.dashboard', 'is_hidden' => true],
                ['menu_key' => 'nav.applyLeave', 'label' => 'Time Off', 'sort_order' => 2],
            ],
        ])->assertOk()->assertJsonFragment(['menu_key' => 'nav.applyLeave', 'label' => 'Time Off']);

        $this->assertTrue(MenuOverride::where('menu_key', 'nav.dashboard')->value('is_hidden'));
    }

    public function test_saving_overrides_prunes_removed_keys(): void
    {
        MenuOverride::factory()->create(['menu_key' => 'nav.stale']);
        $this->actingAs($this->makeUser('administrator'), 'sanctum');

        $this->putJson('/api/menu/overrides', [
            'overrides' => [['menu_key' => 'nav.dashboard', 'is_hidden' => true]],
        ])->assertOk();

        $this->assertDatabaseMissing('menu_overrides', ['menu_key' => 'nav.stale', 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_save_overrides(): void
    {
        $this->actingAs($this->makeUser('hr'), 'sanctum');

        $this->putJson('/api/menu/overrides', ['overrides' => []])->assertStatus(403);
    }

    public function test_user_can_save_own_preferences_and_unfavorite_a_default(): void
    {
        $user = $this->makeUser('employee');
        $this->actingAs($user, 'sanctum');

        // nav.dashboard is a role default; storing an explicit false must persist.
        $this->putJson('/api/menu/preferences', [
            'preferences' => [
                ['menu_key' => 'nav.dashboard', 'is_favorite' => false],
                ['menu_key' => 'nav.myAttendance', 'is_favorite' => true, 'sort_order' => 1],
            ],
        ])->assertOk();

        $this->assertFalse(
            MenuUserPreference::where('user_id', $user->id)->where('menu_key', 'nav.dashboard')->value('is_favorite'),
        );
    }

    public function test_seeder_hides_announcements_menu_by_default(): void
    {
        $this->seed(MenuOverrideSeeder::class);

        $this->assertTrue(MenuOverride::where('menu_key', 'nav.announcements')->value('is_hidden'));
    }

    public function test_seeder_does_not_clobber_an_existing_announcements_override(): void
    {
        MenuOverride::create(['menu_key' => 'nav.announcements', 'is_hidden' => false]);

        $this->seed(MenuOverrideSeeder::class);

        $this->assertFalse(MenuOverride::where('menu_key', 'nav.announcements')->value('is_hidden'));
    }

    public function test_preferences_are_scoped_to_the_acting_user(): void
    {
        $other = $this->makeUser('employee');
        MenuUserPreference::factory()->create([
            'user_id' => $other->id,
            'menu_key' => 'nav.myAttendance',
            'is_favorite' => true,
        ]);

        $this->actingAs($this->makeUser('employee'), 'sanctum');

        $this->getJson('/api/menu/config')
            ->assertOk()
            ->assertJsonPath('myPrefs', []);
    }
}
