<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterUserCustomerVisibilityConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_user_count_and_role_options_include_customer_when_config_is_disabled(): void
    {
        config(['master.hide_customer_from_user_management' => false]);

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');
        Role::findOrCreate('customer', 'web');

        $actor = User::factory()->create();
        $actor->assignRole('superadmin');

        User::factory()->create()->assignRole('admin');
        User::factory()->create()->assignRole('sales');
        User::factory()->create()->assignRole('operations');
        User::factory()->create()->assignRole('customer');

        $this->actingAs($actor);

        $this->get(route('master.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('masters/index')
                    ->where('hideCustomerFromMaster', false)
                    ->where('stats.users', User::query()->whereDoesntHave('ghostUser')->count())
            );

        $this->get(route('master.user.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('masters/users/index')
                    ->where('hideCustomerFromMaster', false)
                    ->where('roleStats.admin', 1)
                    ->where('roleStats.customer', 1)
            );

        $this->get(route('master.user.create'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('masters/users/create')
                    ->where('dataRole', fn ($roles): bool => collect($roles)
                        ->contains(fn (array $role): bool => ($role['value'] ?? null) === 'customer'))
            );
    }

    public function test_master_user_count_and_role_options_hide_customer_when_config_is_enabled(): void
    {
        config(['master.hide_customer_from_user_management' => true]);

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');
        Role::findOrCreate('customer', 'web');

        $actor = User::factory()->create();
        $actor->assignRole('superadmin');

        User::factory()->create()->assignRole('admin');
        User::factory()->create()->assignRole('sales');
        User::factory()->create()->assignRole('operations');
        User::factory()->create()->assignRole('customer');

        $this->actingAs($actor);

        $this->get(route('master.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('masters/index')
                    ->where('hideCustomerFromMaster', true)
                    ->where(
                        'stats.users',
                        User::query()
                            ->whereDoesntHave('ghostUser')
                            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'customer'))
                            ->count()
                    )
            );

        $this->get(route('master.user.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('masters/users/index')
                    ->where('hideCustomerFromMaster', true)
            );

        $this->get(route('master.user.create'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('masters/users/create')
                    ->where('dataRole', fn ($roles): bool => ! collect($roles)
                        ->contains(fn (array $role): bool => ($role['value'] ?? null) === 'customer'))
            );
    }
}
