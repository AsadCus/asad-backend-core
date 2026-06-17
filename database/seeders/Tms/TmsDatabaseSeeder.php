<?php

namespace Database\Seeders\Tms;

use Illuminate\Database\Seeder;

/**
 * TMS-legacy reference seeder. NOT run by the default `migrate:fresh --seed`.
 *
 * Run on demand AFTER the ERP DatabaseSeeder and the TMS migrations:
 *   php artisan migrate --path=database/migrations/tms
 *   php artisan db:seed --class="Database\Seeders\Tms\TmsDatabaseSeeder"
 *
 * EmailDispatchTestSeeder and the full sales pipeline are intentionally left out —
 * seed those manually when exercising email/sales reference flows.
 */
class TmsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            QuotationItemMasterSeeder::class,
            MasterNotesSeeder::class,
            CustomerUserSeeder::class,
            ManifestSeeder::class,
        ]);
    }
}
