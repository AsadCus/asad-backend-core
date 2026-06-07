<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderGraph(): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-ORD-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        return compact('order', 'quotation');
    }

    public function test_order_create_loads_form_without_invoice_number_seed(): void
    {
        $graph = $this->createOrderGraph();

        $response = $this->get(route('order.create', ['quotation_id' => $graph['quotation']->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('orders/create')
            ->where('data.quotation.id', $graph['quotation']->id)
            ->missing('data.invoiceNumberSeed')
        );
    }

    public function test_order_edit_loads_form_without_invoice_number_seed(): void
    {
        $graph = $this->createOrderGraph();

        $response = $this->get(route('order.edit', $graph['order']->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('orders/edit')
            ->where('data.data.id', $graph['order']->id)
            ->missing('data.invoiceNumberSeed')
        );
    }
}
