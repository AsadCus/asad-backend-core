<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageFilterOptionMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_for_filter_marks_selectable_and_private_packages_correctly(): void
    {
        $openSelectable = Package::create([
            'package_number' => 'PKG-OPT-OPEN',
            'name' => 'Open Selectable Package',
            'status' => 'open',
            'total_seats' => 20,
            'seats_left' => 8,
        ]);

        $fullPackage = Package::create([
            'package_number' => 'PKG-OPT-FULL',
            'name' => 'Full Package',
            'status' => 'full',
            'total_seats' => 10,
            'seats_left' => 0,
        ]);

        $privatePackage = Package::create([
            'package_number' => 'PKG-OPT-PRIVATE',
            'name' => 'Private - Group A',
            'status' => 'open',
            'total_seats' => 5,
            'seats_left' => 5,
        ]);

        $options = app(PackageService::class)
            ->getForFilter()
            ->keyBy(fn (array $option): int => (int) $option['value']);

        $this->assertTrue((bool) ($options[$openSelectable->id]['is_selectable'] ?? false));
        $this->assertFalse((bool) ($options[$openSelectable->id]['is_private'] ?? false));

        $this->assertFalse((bool) ($options[$fullPackage->id]['is_selectable'] ?? true));
        $this->assertFalse((bool) ($options[$fullPackage->id]['is_private'] ?? true));

        $this->assertFalse((bool) ($options[$privatePackage->id]['is_selectable'] ?? true));
        $this->assertTrue((bool) ($options[$privatePackage->id]['is_private'] ?? false));
    }
}
