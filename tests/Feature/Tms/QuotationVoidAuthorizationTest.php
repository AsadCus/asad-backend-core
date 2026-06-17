<?php

namespace Tests\Feature\Tms;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class QuotationVoidAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('quotation edit', 'web');

        Role::findOrCreate('superadmin', 'web')->givePermissionTo('quotation edit');
        Role::findOrCreate('admin', 'web')->givePermissionTo('quotation edit');
    }

    private function createReadyQuotation(): Quotation
    {
        return Quotation::create([
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Ready->value,
        ]);
    }

    public function test_non_superadmin_cannot_void_quotation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $quotation = $this->createReadyQuotation();

        $this->actingAs($admin)
            ->put(route('quotation.cancel', $quotation->id))
            ->assertForbidden();

        $quotation->refresh();

        $this->assertSame(QuotationStatus::Ready, $quotation->status);
        $this->assertNull($quotation->voided_by);
        $this->assertNull($quotation->voided_at);
    }

    public function test_superadmin_can_void_quotation_and_void_metadata_is_recorded(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $quotation = $this->createReadyQuotation();

        $this->actingAs($superadmin)
            ->put(route('quotation.cancel', $quotation->id))
            ->assertRedirect(route('quotation.index'));

        $quotation->refresh();

        $this->assertSame(QuotationStatus::Cancelled, $quotation->status);
        $this->assertSame($superadmin->id, $quotation->voided_by);
        $this->assertNotNull($quotation->voided_at);
    }

    public function test_guest_cannot_void_quotation(): void
    {
        $quotation = $this->createReadyQuotation();

        $this->put(route('quotation.cancel', $quotation->id))
            ->assertRedirect(route('login'));

        $this->assertSame(QuotationStatus::Ready, $quotation->fresh()->status);
    }
}
