<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationItemQuickCreateTest extends TestCase
{
    use RefreshDatabase;

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
}
