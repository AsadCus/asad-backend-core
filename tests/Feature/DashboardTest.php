<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\FinancialTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_dashboard_payment_summary_groups_receipts_by_item_header_category(): void
    {
        $this->actingAs(User::factory()->create());

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DB-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Dashboard summary quotation',
        ]);

        $parentHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $childHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $parentHeader->id,
            'description' => 'VIP Addons',
            'is_header' => true,
            'sort_order' => 2,
        ]);

        $leafItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $childHeader->id,
            'description' => 'Wheelchair Service',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 500,
            'sort_order' => 3,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice #1',
            'amount' => 550,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$leafItem->id]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 550,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);
        });

        $response = $this->getJson(route('dashboard.payment-summary-by-period', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertJsonPath('period', 'daily');
        $response->assertJsonPath('total_amount', 550);
        $response->assertJsonPath('receipt_count', 1);

        // Verify categories structure exists
        $categories = $response->json('categories') ?? [];
        $this->assertIsArray($categories);
        $this->assertCount(1, $categories);

        // Verify the umrah_packages category
        $umrahCategory = collect($categories)->firstWhere('category', 'umrah_packages');
        $this->assertIsArray($umrahCategory);
        $this->assertEquals(550, $umrahCategory['amount']);
    }

    public function test_dashboard_payment_summary_pdf_export_returns_pdf_response(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('dashboard.export-payment-summary-pdf', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_payment_summary_service_returns_groups_structure_for_latest_report_template(): void
    {
        $this->actingAs(User::factory()->create());

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DB-002',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Dashboard summary quotation 2',
        ]);

        $parentHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $leafItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $parentHeader->id,
            'description' => 'Test Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 300,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice #2',
            'amount' => 300,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$leafItem->id]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 300,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);
        });

        $report = app(FinancialTransactionService::class)->getPaymentCategorySummary('daily');

        $this->assertSame('daily', $report['mode']);
        $this->assertIsArray($report['groups'] ?? null);
        $this->assertNotEmpty($report['groups']);
        $this->assertArrayHasKey('label', $report['groups'][0]);
        $this->assertArrayHasKey('day_name', $report['groups'][0]);
        $this->assertArrayHasKey('rows', $report['groups'][0]);
    }

    public function test_payment_summary_service_monthly_mode_has_sub_periods_structure(): void
    {
        $this->actingAs(User::factory()->create());

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DB-MONTHLY',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Monthly test quotation',
        ]);

        $parentHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $leafItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $parentHeader->id,
            'description' => 'Test Item Monthly',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 500,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        // Create receipts on different dates in the same month
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice Monthly',
            'amount' => 500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$leafItem->id]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 500,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);
        });

        $report = app(FinancialTransactionService::class)->getPaymentCategorySummary('monthly');

        $this->assertSame('monthly', $report['mode']);
        $this->assertIsArray($report['groups'] ?? null);
        $this->assertNotEmpty($report['groups']);

        $group = $report['groups'][0];
        $this->assertArrayHasKey('label', $group);
        $this->assertArrayHasKey('sub_periods', $group);
        $this->assertIsArray($group['sub_periods']);
        $this->assertNotEmpty($group['sub_periods']);

        // Each sub_period should have label and rows
        $subPeriod = $group['sub_periods'][0];
        $this->assertArrayHasKey('label', $subPeriod);
        $this->assertArrayHasKey('rows', $subPeriod);
        $this->assertIsArray($subPeriod['rows']);
    }

    public function test_payment_summary_service_yearly_mode_has_sub_periods_structure(): void
    {
        $this->actingAs(User::factory()->create());

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DB-YEARLY',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Yearly test quotation',
        ]);

        $parentHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $leafItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $parentHeader->id,
            'description' => 'Test Item Yearly',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 750,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice Yearly',
            'amount' => 750,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$leafItem->id]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 750,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);
        });

        $report = app(FinancialTransactionService::class)->getPaymentCategorySummary('yearly');

        $this->assertSame('yearly', $report['mode']);
        $this->assertIsArray($report['groups'] ?? null);
        $this->assertNotEmpty($report['groups']);

        $group = $report['groups'][0];
        $this->assertArrayHasKey('label', $group);
        $this->assertArrayHasKey('sub_periods', $group);
        $this->assertIsArray($group['sub_periods']);
        $this->assertNotEmpty($group['sub_periods']);

        // Each sub_period should have label (month name) and rows
        $subPeriod = $group['sub_periods'][0];
        $this->assertArrayHasKey('label', $subPeriod);
        $this->assertArrayHasKey('rows', $subPeriod);
        $this->assertIsArray($subPeriod['rows']);
    }

    public function test_removed_salesperson_monthly_dashboard_endpoints_are_not_registered(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNull($routes->getByName('dashboard.sales-period-options'));
        $this->assertNull($routes->getByName('dashboard.quotation-converted-by-salesperson'));
    }
}
