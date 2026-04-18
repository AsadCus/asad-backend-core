<?php

namespace Tests\Feature\Settings;

use App\Models\GhostUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModelNumberFormatSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ghost_admin_can_open_model_number_format_settings_page(): void
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        GhostUser::create(['user_id' => (int) $admin->id]);

        $this->actingAs($admin)
            ->get(route('model-number-formats.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/model-number-formats')
            );
    }

    public function test_non_ghost_admin_cannot_open_model_number_format_settings_page(): void
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('model-number-formats.edit'))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_open_model_number_format_settings_page(): void
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('model-number-formats.edit'))
            ->assertForbidden();
    }
}
