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
        // Menus kept in the registry (so they stay re-enablable from /system/menu) but hidden by
        // default until their feature is rolled out. firstOrCreate = an admin re-enable wins.
        $hiddenByDefault = [
            'nav.announcements',      // company announcements — not rolled out yet
            'nav.applyOvertime',      // overtime self-service — de-emphasised
            'nav.overtimeReport',     // overtime report — de-emphasised
            'nav.overtimeAdmin',      // overtime back-office — de-emphasised
            'nav.attendanceReport',   // reports section — hidden until rolled out
            'nav.leaveReport',
            'nav.businessTripReport',
        ];

        foreach ($hiddenByDefault as $menuKey) {
            MenuOverride::firstOrCreate(['menu_key' => $menuKey], ['is_hidden' => true]);
        }
    }
}
