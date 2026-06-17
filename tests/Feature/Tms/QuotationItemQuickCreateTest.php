<?php

namespace Tests\Feature\Tms;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Tests\TmsTestCase as TestCase;

class QuotationItemQuickCreateTest extends TestCase
{
    public function test_user_can_quick_create_product_service_header_and_child(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(route('quotation-items.quick-create'), [
            'name' => 'Umrah Package Premium',
            'description' => 'Hotel, flight, visa',
            'quantity' => 1,
            'rate' => 1200,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'parent' => ['id', 'description', 'is_header', 'is_optional'],
            'children' => [
                ['id', 'parent_id', 'description', 'is_header', 'quantity', 'rate'],
            ],
        ]);

        $parentId = (int) $response->json('parent.id');
        $childId = (int) $response->json('children.0.id');

        $this->assertDatabaseHas('quotation_item_masters', [
            'id' => $parentId,
            'description' => 'Umrah Package Premium',
            'is_header' => true,
            'is_optional' => true,
        ]);

        $this->assertDatabaseHas('quotation_item_masters', [
            'id' => $childId,
            'parent_id' => $parentId,
            'description' => 'Hotel, flight, visa',
            'is_header' => false,
            'is_optional' => true,
        ]);
    }

    public function test_user_can_quick_create_discount_extension_master(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(route('quotation-items.extensions.quick-create'), [
            'name' => 'Special Promo Discount',
            'type' => 'discount',
            'calculation_mode' => 'percentage',
            'calculation_value' => 12.5,
        ]);

        $response->assertCreated();
        $response->assertJson([
            'name' => 'Special Promo Discount',
            'type' => 'discount',
            'calculation_mode' => 'percentage',
        ]);

        $this->assertDatabaseHas('quotation_extension_masters', [
            'name' => 'Special Promo Discount',
            'type' => 'discount',
            'calculation_mode' => 'percentage',
            'calculation_value' => '12.5000',
        ]);
    }

    public function test_user_can_quick_create_payment_method_master(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(
            route('quotation-items.payment-methods.quick-create'),
            [
                'name' => 'Digital Wallet',
            ],
        );

        $response->assertCreated();
        $response->assertJson([
            'name' => 'Digital Wallet',
            'value' => 'digital_wallet',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('payment_method_masters', [
            'name' => 'Digital Wallet',
            'value' => 'digital_wallet',
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    public function test_store_quotation_requires_customer_selection(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post(route('quotation.store'), [
            'customer_id' => null,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'ready',
            'description' => 'Quotation missing customer',
            'items' => [
                [
                    '_key' => 'item-1',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'sort_order' => 1,
                ],
            ],
            'model' => 'quotation',
            'notes' => [
                [
                    '_key' => 'note-1',
                    'description' => 'Note',
                    'sort_order' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('customer_id');
    }

    public function test_update_quotation_requires_customer_selection(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = Customer::create([
            'user_id' => User::factory()->create()->id,
            'customer_number' => 'CUST-VALID-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Initial quotation',
        ]);

        $quotation->quotationItems()->create([
            'description' => 'Package cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $response = $this->put(route('quotation.update', $quotation->id), [
            'customer_id' => null,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'ready',
            'description' => 'Updated quotation without customer',
            'items' => [
                [
                    'id' => $quotation->quotationItems()->first()->id,
                    '_key' => 'item-existing',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'sort_order' => 1,
                ],
            ],
            'model' => 'quotation',
            'notes' => [
                [
                    '_key' => 'note-1',
                    'description' => 'Note',
                    'sort_order' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('customer_id');
    }
}
