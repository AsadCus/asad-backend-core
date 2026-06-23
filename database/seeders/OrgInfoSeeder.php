<?php

namespace Database\Seeders;

use App\Models\OrgInfo;
use App\Models\OrgUnit;
use Illuminate\Database\Seeder;

class OrgInfoSeeder extends Seeder
{
    /**
     * Vision / Mission / Corporate Value for the holding + each business unit.
     * Placeholder copy — real text is entered via the Informasi Perusahaan editor.
     */
    public function run(): void
    {
        $units = OrgUnit::query()->whereIn('code', ['SMGI', 'SSM', 'GSL', 'SAMU'])->get();

        foreach ($units as $unit) {
            $entries = [
                ['title' => 'Visi', 'body' => "Menjadi perusahaan terdepan di bidangnya — {$unit->name}.", 'sort_order' => 1],
                ['title' => 'Misi', 'body' => "Memberikan solusi, layanan, dan produk terbaik bagi pelanggan {$unit->name}.", 'sort_order' => 2],
                ['title' => 'Nilai Perusahaan', 'body' => 'Integritas, Kolaborasi, Inovasi, Profesionalisme.', 'sort_order' => 3],
            ];

            foreach ($entries as $entry) {
                OrgInfo::updateOrCreate(
                    ['org_unit_id' => $unit->id, 'title' => $entry['title']],
                    $entry,
                );
            }
        }
    }
}
