<?php

namespace Database\Seeders;

use App\Models\MenuOverride;
use Illuminate\Database\Seeder;

class MenuOverrideSeeder extends Seeder
{
    /**
     * Default global menu overrides. `firstOrCreate` so these only set the initial default —
     * an administrator can re-enable/edit them later without the seeder clobbering the change.
     */
    public function run(): void
    {
        // Announcements menu is hidden by default until the feature is rolled out.
        MenuOverride::firstOrCreate(
            ['menu_key' => 'nav.announcements'],
            ['is_hidden' => true],
        );
    }
}
