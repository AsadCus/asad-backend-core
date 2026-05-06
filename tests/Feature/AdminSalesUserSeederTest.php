<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Country;
use App\Models\GhostUser;
use App\Models\User;
use Database\Seeders\AdminSalesUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSalesUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_kherman_as_ghost_superadmin_with_asad_country_scope(): void
    {
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('operations', 'web');

        $singapore = Country::factory()->create(['name' => 'Singapore']);
        $malaysia = Country::factory()->create(['name' => 'Malaysia']);

        $this->seed(AdminSalesUserSeeder::class);

        $kherman = User::query()->where('email', 'kherman@example.com')->first();

        $this->assertNotNull($kherman);
        $this->assertSame('Kherman', $kherman->name);
        $this->assertTrue($kherman->hasRole('superadmin'));

        $khermanAdmin = Admin::query()->where('user_id', (int) $kherman->id)->first();
        $this->assertNotNull($khermanAdmin);

        $khermanCountryIds = $khermanAdmin->country_ids ?? [];
        sort($khermanCountryIds);

        $expectedCountryIds = [(int) $singapore->id, (int) $malaysia->id];
        sort($expectedCountryIds);

        $this->assertSame($expectedCountryIds, $khermanCountryIds);
        $this->assertDatabaseHas('ghost_users', [
            'user_id' => (int) $kherman->id,
        ]);

        $asad = User::query()->where('email', 'asad@example.com')->first();
        $this->assertNotNull($asad);

        $asadAdmin = Admin::query()->where('user_id', (int) $asad->id)->first();
        $this->assertNotNull($asadAdmin);

        $asadCountryIds = $asadAdmin->country_ids ?? [];
        sort($asadCountryIds);

        $this->assertSame($asadCountryIds, $khermanCountryIds);
        $this->assertDatabaseHas('ghost_users', [
            'user_id' => (int) $asad->id,
        ]);

        $this->assertSame(2, GhostUser::query()->count());
    }
}
