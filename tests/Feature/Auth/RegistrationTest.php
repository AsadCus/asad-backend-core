<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase;

class RegistrationTest extends TmsTestCase
{
    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $this->seed(RolePermissionSeeder::class);

        // Customer registration assigns the customer role and notifies admin/sales;
        // these TMS roles are no longer seeded by RolePermissionSeeder after the ERP/TMS split.
        foreach (['customer', 'admin', 'sales'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
