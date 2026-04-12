<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Manifest;
use App\Models\Package;
use App\Models\Sales;
use App\Models\User;
use App\Services\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManifestCountryScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_country_sees_only_manifests_in_same_country(): void
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

        $branchA = Branch::create([
            'name' => 'KL Branch',
            'country_id' => $countryA->id,
        ]);

        $adminUser = User::factory()->create();
        $adminUser->assignRole($adminRole);

        Admin::query()->create([
            'user_id' => $adminUser->id,
            'branch_id' => $branchA->id,
            'country_id' => $countryA->id,
            'branch_ids' => [$branchA->id],
            'country_ids' => [$countryA->id],
        ]);

        $packageA = Package::create([
            'package_number' => 'PKG-COUNTRY-A',
            'name' => 'Package Country A',
            'status' => 'open',
            'country_id' => $countryA->id,
        ]);

        $packageB = Package::create([
            'package_number' => 'PKG-COUNTRY-B',
            'name' => 'Package Country B',
            'status' => 'open',
            'country_id' => $countryB->id,
        ]);

        $manifestA = Manifest::create([
            'package_id' => $packageA->id,
            'manifest_number' => 'MAN-COUNTRY-A',
        ]);

        Manifest::create([
            'package_id' => $packageB->id,
            'manifest_number' => 'MAN-COUNTRY-B',
        ]);

        $this->actingAs($adminUser);

        $manifests = app(ManifestService::class)->getForDataTable();

        $this->assertCount(1, $manifests);
        $this->assertSame($manifestA->id, $manifests->first()['id']);
    }

    public function test_admin_without_country_sees_all_manifests(): void
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

        $adminUser = User::factory()->create();
        $adminUser->assignRole($adminRole);

        Admin::query()->create([
            'user_id' => $adminUser->id,
            'branch_id' => null,
            'country_id' => null,
            'branch_ids' => [],
            'country_ids' => [],
        ]);

        $packageA = Package::create([
            'package_number' => 'PKG-COUNTRY-A2',
            'name' => 'Package Country A2',
            'status' => 'open',
            'country_id' => $countryA->id,
        ]);

        $packageB = Package::create([
            'package_number' => 'PKG-COUNTRY-B2',
            'name' => 'Package Country B2',
            'status' => 'open',
            'country_id' => $countryB->id,
        ]);

        Manifest::create([
            'package_id' => $packageA->id,
            'manifest_number' => 'MAN-COUNTRY-A2',
        ]);

        Manifest::create([
            'package_id' => $packageB->id,
            'manifest_number' => 'MAN-COUNTRY-B2',
        ]);

        $this->actingAs($adminUser);

        $manifests = app(ManifestService::class)->getForDataTable();

        $this->assertCount(2, $manifests);
    }

    public function test_sales_with_country_sees_only_manifests_in_same_country_when_scope_enabled(): void
    {
        config(['data_scope.enabled' => true]);

        $salesRole = Role::query()->firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);

        $countryA = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $branchA = Branch::create([
            'name' => 'KL Branch',
            'country_id' => $countryA->id,
        ]);

        $salesUser = User::factory()->create();
        $salesUser->assignRole($salesRole);

        Sales::query()->create([
            'user_id' => $salesUser->id,
            'branch_id' => $branchA->id,
            'country_id' => $countryA->id,
            'branch_ids' => [$branchA->id],
            'country_ids' => [$countryA->id],
        ]);

        $packageA = Package::create([
            'package_number' => 'PKG-SALES-A',
            'name' => 'Package Sales A',
            'status' => 'open',
            'country_id' => $countryA->id,
        ]);

        $packageB = Package::create([
            'package_number' => 'PKG-SALES-B',
            'name' => 'Package Sales B',
            'status' => 'open',
            'country_id' => $countryB->id,
        ]);

        $manifestA = Manifest::create([
            'package_id' => $packageA->id,
            'manifest_number' => 'MAN-SALES-A',
        ]);

        Manifest::create([
            'package_id' => $packageB->id,
            'manifest_number' => 'MAN-SALES-B',
        ]);

        $this->actingAs($salesUser);

        $manifests = app(ManifestService::class)->getForDataTable();

        $this->assertCount(1, $manifests);
        $this->assertSame($manifestA->id, $manifests->first()['id']);
    }

    public function test_scope_disabled_allows_sales_to_see_all_manifests(): void
    {
        config(['data_scope.enabled' => false]);

        $salesRole = Role::query()->firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);

        $countryA = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $branchA = Branch::create([
            'name' => 'KL Branch',
            'country_id' => $countryA->id,
        ]);

        $salesUser = User::factory()->create();
        $salesUser->assignRole($salesRole);

        Sales::query()->create([
            'user_id' => $salesUser->id,
            'branch_id' => $branchA->id,
            'country_id' => $countryA->id,
            'branch_ids' => [$branchA->id],
            'country_ids' => [$countryA->id],
        ]);

        $packageA = Package::create([
            'package_number' => 'PKG-SCOPE-OFF-A',
            'name' => 'Package Scope Off A',
            'status' => 'open',
            'country_id' => $countryA->id,
        ]);

        $packageB = Package::create([
            'package_number' => 'PKG-SCOPE-OFF-B',
            'name' => 'Package Scope Off B',
            'status' => 'open',
            'country_id' => $countryB->id,
        ]);

        Manifest::create([
            'package_id' => $packageA->id,
            'manifest_number' => 'MAN-SCOPE-OFF-A',
        ]);

        Manifest::create([
            'package_id' => $packageB->id,
            'manifest_number' => 'MAN-SCOPE-OFF-B',
        ]);

        $this->actingAs($salesUser);

        $manifests = app(ManifestService::class)->getForDataTable();

        $this->assertCount(2, $manifests);
    }
}
