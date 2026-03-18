<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use App\Services\EnquiryService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\QuotationService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnquirySalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('admin', 'web');
    }

    public function test_status_transition_sets_handled_by_and_supports_contacted_to_confirmed(): void
    {
        $salesUser = User::factory()->create();
        $salesUser->assignRole('sales');

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Status Flow Test',
            'contact_number' => '0123456789',
            'email' => 'status-flow@test.com',
            'created_by' => $salesUser->id,
        ]);

        $this->actingAs($salesUser);

        app(EnquiryService::class)->transitionStatus($enquiry->id, EnquiryStatus::Contacted->value);

        $enquiry->refresh();

        $this->assertSame(EnquiryStatus::Contacted, $enquiry->status);
        $this->assertSame($salesUser->id, $enquiry->handled_by);
        $this->assertTrue(EnquiryStatus::Contacted->canTransitionTo(EnquiryStatus::Confirmed));
    }

    public function test_sales_only_sees_confirmed_customer_groups_handled_by_them(): void
    {
        $salesA = User::factory()->create();
        $salesA->assignRole('sales');

        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $package = Package::create([
            'package_number' => 'PKG-SCOPE-001',
            'name' => 'Scope Package',
            'status' => 'open',
        ]);

        $groupA = $this->createConfirmationGroupHandledBy($salesA->id, $package->id, 'a');
        $this->createConfirmationGroupHandledBy($salesB->id, $package->id, 'b');

        $this->actingAs($salesA);

        $groups = app(CustomerConfirmationService::class)->getForGroupedIndex(true);

        $this->assertCount(1, $groups);
        $this->assertSame($groupA->id, (int) $groups[0]['id']);
    }

    public function test_sales_only_sees_pipeline_records_for_their_handled_enquiries(): void
    {
        $salesA = User::factory()->create();
        $salesA->assignRole('sales');

        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $pipelineA = $this->createSalesPipeline($salesA->id, 'a');
        $this->createSalesPipeline($salesB->id, 'b');

        $this->actingAs($salesA);

        $quotations = app(QuotationService::class)->getForDataTable(['sales_id' => $salesA->id]);
        $orders = app(OrderService::class)->getForDataTable(['sales_id' => $salesA->id]);
        $invoices = app(InvoiceService::class)->getForDataTable(['sales_id' => $salesA->id]);
        $receipts = app(ReceiptService::class)->getForDataTable(['sales_id' => $salesA->id]);

        $this->assertCount(1, $quotations);
        $this->assertSame($pipelineA['quotation']->id, (int) $quotations->first()['id']);

        $this->assertCount(1, $orders);
        $this->assertSame($pipelineA['order']->id, (int) $orders->first()['id']);

        $this->assertCount(1, $invoices);
        $this->assertSame($pipelineA['invoice']->id, (int) $invoices->first()['id']);

        $this->assertCount(1, $receipts);
        $this->assertSame($pipelineA['receipt']->id, (int) $receipts->first()['id']);
    }

    private function createConfirmationGroupHandledBy(int $handledBy, int $packageId, string $suffix): CustomerConfirmation
    {
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => "Handled {$suffix}",
            'contact_number' => "01234567{$suffix}",
            'email' => "handled-{$suffix}@test.com",
            'created_by' => $handledBy,
            'handled_by' => $handledBy,
        ]);

        $customerUser = User::factory()->create([
            'name' => "Customer {$suffix}",
            'email' => "customer-{$suffix}@test.com",
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => "CUST-{$suffix}",
        ]);

        $group = CustomerConfirmation::create([
            'enquiry_id' => $enquiry->id,
            'created_by' => $handledBy,
            'package_id' => $packageId,
        ]);

        CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'sharing_plan' => 'double',
        ]);

        return $group;
    }

    /**
     * @return array{quotation: Quotation, order: Order, invoice: Invoice, receipt: Receipt}
     */
    private function createSalesPipeline(int $handledBy, string $suffix): array
    {
        $package = Package::create([
            'package_number' => "PKG-PIPE-{$suffix}",
            'name' => "Pipeline {$suffix}",
            'status' => 'open',
        ]);

        $group = $this->createConfirmationGroupHandledBy($handledBy, $package->id, "pipe-{$suffix}");

        $customerId = $group->leader()?->customer_id;
        $this->assertNotNull($customerId);

        $quotation = Quotation::create([
            'customer_id' => (int) $customerId,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => "Quotation {$suffix}",
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => "Invoice {$suffix}",
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(1)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        return [
            'quotation' => $quotation,
            'order' => $order,
            'invoice' => $invoice,
            'receipt' => $receipt,
        ];
    }
}
