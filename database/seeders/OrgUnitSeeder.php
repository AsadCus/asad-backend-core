<?php

namespace Database\Seeders;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use Illuminate\Database\Seeder;

class OrgUnitSeeder extends Seeder
{
    /**
     * Real SMGI structure: holding + 3 business units, each with an HQ branch.
     * The first BU carries an illustrative department/division so the full
     * 5-level tree exists for nesting/scope tests.
     */
    public function run(): void
    {
        $tree = [
            [
                'type' => OrgUnitType::Holding,
                'name' => 'SMGI Group',
                'code' => 'SMGI',
                'email' => 'info@smgi.example.com',
                'logo_path' => '/Logo Icon SMGI.png',
                'children' => [
                    [
                        'type' => OrgUnitType::BusinessUnit,
                        'name' => 'PT Samu Sinergi Mandiri',
                        'code' => 'SSM',
                        'logo_path' => '/Logo Icon Samu Sinergi Mandiri.png',
                        'children' => [
                            [
                                'type' => OrgUnitType::Branch,
                                'name' => 'SSM Head Office',
                                'code' => 'SSM-HO',
                                'geofence_radius_meters' => 100,
                                'children' => [
                                    [
                                        'type' => OrgUnitType::Department,
                                        'name' => 'Human Resources',
                                        'code' => 'SSM-HR',
                                        'children' => [
                                            ['type' => OrgUnitType::Division, 'name' => 'People Operations', 'code' => 'SSM-HR-POPS'],
                                        ],
                                    ],
                                    ['type' => OrgUnitType::Department, 'name' => 'Finance', 'code' => 'SSM-FIN'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => OrgUnitType::BusinessUnit,
                        'name' => 'PT Global Sinergi Laboratorium',
                        'code' => 'GSL',
                        'logo_path' => '/Logo Icon Global Sinergi Laboratorium.png',
                        'children' => [
                            ['type' => OrgUnitType::Branch, 'name' => 'GSL Head Office', 'code' => 'GSL-HO', 'geofence_radius_meters' => 100],
                        ],
                    ],
                    [
                        'type' => OrgUnitType::BusinessUnit,
                        'name' => 'PT Sinergi Akbar Mandiri Utama',
                        'code' => 'SAMU',
                        'logo_path' => '/Logo Icon Sinergi Akbar Mandiri Utama.png',
                        'children' => [
                            ['type' => OrgUnitType::Branch, 'name' => 'SAMU Head Office', 'code' => 'SAMU-HO', 'geofence_radius_meters' => 100],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($tree as $node) {
            $this->seedNode($node, null);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function seedNode(array $node, ?int $parentId): void
    {
        $children = $node['children'] ?? [];
        unset($node['children']);

        $unit = OrgUnit::updateOrCreate(
            ['code' => $node['code']],
            array_merge($node, ['parent_id' => $parentId, 'is_active' => true]),
        );

        foreach ($children as $child) {
            $this->seedNode($child, $unit->id);
        }
    }
}
