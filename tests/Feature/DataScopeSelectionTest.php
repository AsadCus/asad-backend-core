<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Country;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DataScopeSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_persist_selected_country_scope_and_data_scope_uses_it(): void
    {
        config(['data_scope.enabled' => true]);

        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $countryA = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        Admin::query()->create([
            'user_id' => $user->id,
            'branch_id' => null,
            'country_id' => $countryA->id,
            'branch_ids' => [],
            'country_ids' => [$countryA->id, $countryB->id],
        ]);

        $this->actingAs($user)
            ->post(route('data-scope.countries.update'), [
                'country_ids' => [$countryB->id],
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame([$countryB->id], $user->selected_country_ids);
        $this->assertSame([$countryB->id], DataScope::scopedCountryIds($user));
        $this->assertSame([$countryA->id, $countryB->id], DataScope::assignableCountryIds($user));
    }
}
