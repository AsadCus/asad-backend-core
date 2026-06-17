<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Country;
use App\Models\FinancialYear;
use App\Models\GhostUser;
use App\Models\Operation;
use App\Models\Sales;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_seeds_only_master_data_and_core_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Country::count());
        $this->assertGreaterThan(0, FinancialYear::count());

        $this->assertSame(3, User::role('superadmin')->count());
        $this->assertSame(2, User::role('sales')->count());
        $this->assertSame(1, User::role('operations')->count());
        $this->assertSame(3, Admin::count());
        $this->assertSame(2, Sales::count());
        $this->assertSame(1, Operation::count());
        $this->assertSame(2, GhostUser::count());
        $this->assertDatabaseHas('ghost_users', [
            'user_id' => (int) User::query()->where('email', 'asad@example.com')->value('id'),
        ]);

        $singaporeId = (int) Country::query()->where('name', 'Singapore')->value('id');
        $malaysiaId = (int) Country::query()->where('name', 'Malaysia')->value('id');

        $this->assertGreaterThan(0, $singaporeId);
        $this->assertGreaterThan(0, $malaysiaId);

        $asadAdmin = User::query()->where('email', 'asad@example.com')->firstOrFail()->admin;
        $this->assertNotNull($asadAdmin);
        $this->assertSame($singaporeId, (int) ($asadAdmin->country_id ?? 0));
        $this->assertEqualsCanonicalizing(
            [$singaporeId, $malaysiaId],
            array_map('intval', $asadAdmin->country_ids ?? []),
        );

        $normalAdmin = User::query()->where('email', 'admin@example.com')->firstOrFail()->admin;
        $this->assertNotNull($normalAdmin);
        $this->assertSame($singaporeId, (int) ($normalAdmin->country_id ?? 0));
        $this->assertEqualsCanonicalizing(
            [$singaporeId],
            array_map('intval', $normalAdmin->country_ids ?? []),
        );

        $salesUsers = User::role('sales')->with('sales')->get();
        foreach ($salesUsers as $salesUser) {
            $this->assertNotNull($salesUser->sales);
            $this->assertSame($singaporeId, (int) ($salesUser->sales?->country_id ?? 0));
            $this->assertEqualsCanonicalizing(
                [$singaporeId],
                array_map('intval', $salesUser->sales?->country_ids ?? []),
            );
        }

        $operationsUser = User::query()->where('email', 'operations@example.com')->firstOrFail();
        $this->assertSame('operations', $operationsUser->getRoleNames()->first());
        $this->assertNotNull($operationsUser->operation);
        $this->assertSame($singaporeId, (int) ($operationsUser->operation?->country_id ?? 0));
        $this->assertEqualsCanonicalizing(
            [$singaporeId],
            array_map('intval', $operationsUser->operation?->country_ids ?? []),
        );

        // TMS tables are not part of the ERP migrate:fresh --seed; their absence is
        // asserted by the Tms suite, not here.
    }
}
