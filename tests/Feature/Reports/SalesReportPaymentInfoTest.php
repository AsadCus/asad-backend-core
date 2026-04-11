<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesReportPaymentInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_preview_displays_paid_and_outstanding_installments_and_excludes_refund_rows(): void
    {
        Permission::findOrCreate('sales view', 'web');
        Role::findOrCreate('sales', 'web');

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('sales view');

        $country = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $branch = Branch::create([
            'name' => 'HQ Branch',
            'country_id' => $country->id,
        ]);

        $salesUser = User::factory()->create([
            'name' => 'Sales Preview User',
            'email' => 'sales-preview@example.com',
        ]);
        $salesUser->assignRole('sales');

        Sales::create([
            'user_id' => $salesUser->id,
            'branch_id' => $branch->id,
        ]);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-SALES-RPT-001',
            'is_active' => true,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'created_by' => $salesUser->id,
            'quotation_date' => '2026-04-01',
            'expiry_date' => '2026-05-01',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'First installment',
            'amount' => 2622.50,
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-01',
            'status' => 'paid',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second installment',
            'amount' => 2622.50,
            'invoice_date' => '2026-04-10',
            'due_date' => '2026-04-10',
            'status' => 'outstanding',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Refund installment',
            'amount' => -500.00,
            'invoice_date' => '2026-04-15',
            'due_date' => '2026-04-15',
            'status' => 'refund',
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('sales.preview', ['sale' => $salesUser->id]));

        $response->assertOk();
        $response->assertSee('Payment Info');
        $response->assertSee('1st Payment');
        $response->assertSee('2nd Payment');
        $response->assertSee('Paid');
        $response->assertSee('Outstanding');
        $response->assertSeeInOrder([
            '1st Payment',
            '2,622.50',
            'Paid',
            '2nd Payment',
            '0.00',
            'Outstanding',
        ]);
        $response->assertDontSee('Refund installment');
    }
}
