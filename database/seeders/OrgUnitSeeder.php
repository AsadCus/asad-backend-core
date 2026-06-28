<?php

namespace Database\Seeders;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use Illuminate\Database\Seeder;

class OrgUnitSeeder extends Seeder
{
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
                    ['type' => OrgUnitType::Department, 'name' => 'Human Resources', 'code' => 'SMGI-HR'],
                    ['type' => OrgUnitType::Department, 'name' => 'Finance & Accounting', 'code' => 'SMGI-FIN'],
                    ['type' => OrgUnitType::Department, 'name' => 'Marketing & Sales', 'code' => 'SMGI-MKT'],
                    ['type' => OrgUnitType::Department, 'name' => 'Operasional', 'code' => 'SMGI-OPS'],
                    ['type' => OrgUnitType::Department, 'name' => 'IT & Technology', 'code' => 'SMGI-IT'],
                    ['type' => OrgUnitType::Department, 'name' => 'Legal & Compliance', 'code' => 'SMGI-LEGAL'],
                    ['type' => OrgUnitType::Department, 'name' => 'General Affairs', 'code' => 'SMGI-GA'],
                    [
                        'type' => OrgUnitType::BusinessUnit,
                        'name' => 'PT Samu Sinergi Mandiri',
                        'code' => 'SSM',
                        'logo_path' => '/Logo Icon Samu Sinergi Mandiri.png',
                        'children' => [
                            ['type' => OrgUnitType::Department, 'name' => 'Human Resources', 'code' => 'SSM-HR'],
                            ['type' => OrgUnitType::Department, 'name' => 'Finance & Accounting', 'code' => 'SSM-FIN'],
                            ['type' => OrgUnitType::Department, 'name' => 'Marketing & Sales', 'code' => 'SSM-MKT'],
                            ['type' => OrgUnitType::Department, 'name' => 'Operasional', 'code' => 'SSM-OPS'],
                            ['type' => OrgUnitType::Department, 'name' => 'IT & Technology', 'code' => 'SSM-IT'],
                            ['type' => OrgUnitType::Department, 'name' => 'Legal & Compliance', 'code' => 'SSM-LEGAL'],
                            ['type' => OrgUnitType::Department, 'name' => 'General Affairs', 'code' => 'SSM-GA'],
                        ],
                    ],
                    [
                        'type' => OrgUnitType::BusinessUnit,
                        'name' => 'PT Global Sinergi Laboratorium',
                        'code' => 'GSL',
                        'logo_path' => '/Logo Icon Global Sinergi Laboratorium.png',
                        'children' => [
                            ['type' => OrgUnitType::Department, 'name' => 'Human Resources', 'code' => 'GSL-HR'],
                            ['type' => OrgUnitType::Department, 'name' => 'Finance & Accounting', 'code' => 'GSL-FIN'],
                            ['type' => OrgUnitType::Department, 'name' => 'Marketing & Sales', 'code' => 'GSL-MKT'],
                            ['type' => OrgUnitType::Department, 'name' => 'Operasional', 'code' => 'GSL-OPS'],
                            ['type' => OrgUnitType::Department, 'name' => 'IT & Technology', 'code' => 'GSL-IT'],
                            ['type' => OrgUnitType::Department, 'name' => 'Legal & Compliance', 'code' => 'GSL-LEGAL'],
                            ['type' => OrgUnitType::Department, 'name' => 'General Affairs', 'code' => 'GSL-GA'],
                        ],
                    ],
                    [
                        'type' => OrgUnitType::BusinessUnit,
                        'name' => 'PT Sinergi Akbar Mandiri Utama',
                        'code' => 'SAMU',
                        'logo_path' => '/Logo Icon Sinergi Akbar Mandiri Utama.png',
                        'children' => [
                            ['type' => OrgUnitType::Department, 'name' => 'Human Resources', 'code' => 'SAMU-HR'],
                            ['type' => OrgUnitType::Department, 'name' => 'Finance & Accounting', 'code' => 'SAMU-FIN'],
                            ['type' => OrgUnitType::Department, 'name' => 'Marketing & Sales', 'code' => 'SAMU-MKT'],
                            ['type' => OrgUnitType::Department, 'name' => 'Operasional', 'code' => 'SAMU-OPS'],
                            ['type' => OrgUnitType::Department, 'name' => 'IT & Technology', 'code' => 'SAMU-IT'],
                            ['type' => OrgUnitType::Department, 'name' => 'Legal & Compliance', 'code' => 'SAMU-LEGAL'],
                            ['type' => OrgUnitType::Department, 'name' => 'General Affairs', 'code' => 'SAMU-GA'],
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
