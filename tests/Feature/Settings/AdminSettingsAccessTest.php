<?php

namespace Tests\Feature\Settings;

use App\Models\GhostUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ghost_admin_can_open_report_template_and_appearance_settings(): void
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        GhostUser::create(['user_id' => (int) $admin->id]);

        $this->actingAs($admin)
            ->get(route('report-template.edit'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('appearance.edit'))
            ->assertOk();
    }

    public function test_non_ghost_admin_cannot_open_report_template_but_can_open_appearance_settings(): void
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('report-template.edit'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('appearance.edit'))
            ->assertOk();
    }

    public function test_non_admin_cannot_open_report_template_and_appearance_settings(): void
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('report-template.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertForbidden();
    }
}
