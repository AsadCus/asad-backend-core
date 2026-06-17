<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base test case for the TMS-legacy reference suite.
 *
 * TMS migrations live in database/migrations/tms and are excluded from the default
 * migrate:fresh that RefreshDatabase runs. This hook migrates that path on top so the
 * legacy tests have their schema. Run the suite with:
 *   php artisan test tests/Unit/Tms tests/Feature/Tms
 */
abstract class TmsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function afterRefreshingDatabase()
    {
        $this->artisan('migrate', [
            '--path' => 'database/migrations/tms',
            '--realpath' => false,
        ]);
    }
}
