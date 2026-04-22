<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\Sales;
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
        config()->set('data_scope.enabled', true);
        config()->set('data_scope.scope_sales_ownership', true);

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

    public function test_sales_sees_all_confirmed_customer_groups_when_data_scope_disabled(): void
    {
        config()->set('data_scope.enabled', false);
        config()->set('data_scope.scope_sales_ownership', false);

        $salesA = User::factory()->create();
        $salesA->assignRole('sales');

        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $package = Package::create([
            'package_number' => 'PKG-SCOPE-002',
            'name' => 'Scope Package Disabled',
            'status' => 'open',
        ]);

        $groupA = $this->createConfirmationGroupHandledBy($salesA->id, $package->id, 'scope-disabled-a');
        $groupB = $this->createConfirmationGroupHandledBy($salesB->id, $package->id, 'scope-disabled-b');

        $this->actingAs($salesA);

        $groups = app(CustomerConfirmationService::class)->getForGroupedIndex(true);

        $this->assertCount(2, $groups);
        $groupIds = collect($groups)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains($groupA->id, $groupIds);
        $this->assertContains($groupB->id, $groupIds);
    }

    public function test_sales_sees_enquiries_and_confirmations_in_scoped_country_even_when_handled_by_other_sales(): void
    {
        config()->set('data_scope.enabled', true);
        config()->set('data_scope.scope_sales_ownership', true);
        config()->set('data_scope.mode', 'country');

        $countrySingapore = Country::create([
            'name' => 'Singapore',
            'adjective' => 'Singaporean',
        ]);

        $countryMalaysia = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $salesA = User::factory()->create();
        $salesA->assignRole('sales');
        Sales::query()->create([
            'user_id' => $salesA->id,
            'country_id' => $countrySingapore->id,
            'country_ids' => [$countrySingapore->id],
            'branch_ids' => [],
        ]);

        $salesB = User::factory()->create();
        $salesB->assignRole('sales');
        Sales::query()->create([
            'user_id' => $salesB->id,
            'country_id' => $countryMalaysia->id,
            'country_ids' => [$countryMalaysia->id],
            'branch_ids' => [],
        ]);

        $package = Package::create([
            'package_number' => 'PKG-SCOPE-COUNTRY-001',
            'name' => 'Country Scope Package',
            'status' => 'open',
        ]);

        $visibleGroup = $this->createConfirmationGroupHandledBy(
            handledBy: $salesB->id,
            packageId: $package->id,
            suffix: 'country-visible',
            countryId: $countrySingapore->id,
        );

        $hiddenGroup = $this->createConfirmationGroupHandledBy(
            handledBy: $salesB->id,
            packageId: $package->id,
            suffix: 'country-hidden',
            countryId: $countryMalaysia->id,
        );

        $this->actingAs($salesA);

        $enquiryRows = app(EnquiryService::class)->getForDataTable();
        $enquiryIds = collect($enquiryRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $groupRows = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $groupIds = collect($groupRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains((int) $visibleGroup->enquiry_id, $enquiryIds);
        $this->assertNotContains((int) $hiddenGroup->enquiry_id, $enquiryIds);
        $this->assertContains($visibleGroup->id, $groupIds);
        $this->assertNotContains($hiddenGroup->id, $groupIds);
    }

    public function test_sales_does_not_see_own_handled_enquiries_and_confirmations_outside_selected_scope(): void
    {
        config()->set('data_scope.enabled', true);
        config()->set('data_scope.scope_sales_ownership', true);
        config()->set('data_scope.mode', 'country');

        $countrySingapore = Country::create([
            'name' => 'Singapore',
            'adjective' => 'Singaporean',
        ]);

        $countryMalaysia = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $salesA = User::factory()->create();
        $salesA->assignRole('sales');
        Sales::query()->create([
            'user_id' => $salesA->id,
            'country_id' => $countrySingapore->id,
            'country_ids' => [$countrySingapore->id],
            'branch_ids' => [],
        ]);

        $ownedGroupOutsideLocation = $this->createConfirmationGroupHandledBy(
            handledBy: $salesA->id,
            packageId: Package::create([
                'package_number' => 'PKG-SCOPE-OWN-001',
                'name' => 'Own Handled Scope Package',
                'status' => 'open',
            ])->id,
            suffix: 'own-outside-scope',
            countryId: $countryMalaysia->id,
        );

        $this->actingAs($salesA);

        $enquiryRows = app(EnquiryService::class)->getForDataTable();
        $enquiryIds = collect($enquiryRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $groupRows = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $groupIds = collect($groupRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertNotContains((int) $ownedGroupOutsideLocation->enquiry_id, $enquiryIds);
        $this->assertNotContains($ownedGroupOutsideLocation->id, $groupIds);
    }

    public function test_sales_branch_scope_uses_branch_and_branch_country_for_enquiry_and_confirmation_visibility(): void
    {
        config()->set('data_scope.enabled', true);
        config()->set('data_scope.scope_sales_ownership', true);
        config()->set('data_scope.mode', 'branch');

        $countrySingapore = Country::create([
            'name' => 'Singapore',
            'adjective' => 'Singaporean',
        ]);

        $countryMalaysia = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $branchSingapore = Branch::create([
            'name' => 'Singapore Branch',
            'country_id' => $countrySingapore->id,
        ]);

        $branchMalaysia = Branch::create([
            'name' => 'Malaysia Branch',
            'country_id' => $countryMalaysia->id,
        ]);

        $salesA = User::factory()->create();
        $salesA->assignRole('sales');
        Sales::query()->create([
            'user_id' => $salesA->id,
            'branch_id' => $branchSingapore->id,
            'branch_ids' => [$branchSingapore->id],
            'country_ids' => [],
        ]);

        $salesB = User::factory()->create();
        $salesB->assignRole('sales');
        Sales::query()->create([
            'user_id' => $salesB->id,
            'branch_id' => $branchMalaysia->id,
            'branch_ids' => [$branchMalaysia->id],
            'country_ids' => [],
        ]);

        $package = Package::create([
            'package_number' => 'PKG-SCOPE-BRANCH-001',
            'name' => 'Branch Scope Package',
            'status' => 'open',
        ]);

        $visibleByBranch = $this->createConfirmationGroupHandledBy(
            handledBy: $salesB->id,
            packageId: $package->id,
            suffix: 'branch-visible',
            countryId: null,
            branchId: $branchSingapore->id,
        );

        $visibleByCountryFallback = $this->createConfirmationGroupHandledBy(
            handledBy: $salesB->id,
            packageId: $package->id,
            suffix: 'branch-country-visible',
            countryId: $countrySingapore->id,
            branchId: null,
        );

        $hiddenGroup = $this->createConfirmationGroupHandledBy(
            handledBy: $salesB->id,
            packageId: $package->id,
            suffix: 'branch-hidden',
            countryId: $countryMalaysia->id,
            branchId: $branchMalaysia->id,
        );

        $this->actingAs($salesA);

        $enquiryRows = app(EnquiryService::class)->getForDataTable();
        $enquiryIds = collect($enquiryRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $groupRows = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $groupIds = collect($groupRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains((int) $visibleByBranch->enquiry_id, $enquiryIds);
        $this->assertContains((int) $visibleByCountryFallback->enquiry_id, $enquiryIds);
        $this->assertNotContains((int) $hiddenGroup->enquiry_id, $enquiryIds);
        $this->assertContains($visibleByBranch->id, $groupIds);
        $this->assertContains($visibleByCountryFallback->id, $groupIds);
        $this->assertNotContains($hiddenGroup->id, $groupIds);
    }

    public function test_sales_sees_all_quotation_order_invoice_and_receipt_data_when_sales_ownership_scope_is_disabled(): void
    {
        config()->set('data_scope.enabled', true);
        config()->set('data_scope.scope_sales_ownership', false);

        $salesA = User::factory()->create();
        $salesA->assignRole('sales');

        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $customerA = Customer::create([
            'user_id' => User::factory()->create()->id,
            'customer_number' => 'CUST-SALES-A',
        ]);

        $customerB = Customer::create([
            'user_id' => User::factory()->create()->id,
            'customer_number' => 'CUST-SALES-B',
        ]);

        $quotationA = Quotation::create([
            'customer_id' => $customerA->id,
            'created_by' => $salesA->id,
            'quotation_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $quotationB = Quotation::create([
            'customer_id' => $customerB->id,
            'created_by' => $salesB->id,
            'quotation_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $orderA = Order::create([
            'quotation_id' => $quotationA->id,
        ]);

        $orderB = Order::create([
            'quotation_id' => $quotationB->id,
        ]);

        $invoiceA = Invoice::create([
            'order_id' => $orderA->id,
            'description' => 'Invoice A',
            'payment_method' => 'cash',
            'amount' => 100,
            'invoice_date' => now()->toDateString(),
            'status' => 'outstanding',
        ]);

        $invoiceB = Invoice::create([
            'order_id' => $orderB->id,
            'description' => 'Invoice B',
            'payment_method' => 'cash',
            'amount' => 200,
            'invoice_date' => now()->toDateString(),
            'status' => 'outstanding',
        ]);

        Receipt::create([
            'invoice_id' => $invoiceA->id,
            'amount' => 100,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        Receipt::create([
            'invoice_id' => $invoiceB->id,
            'amount' => 200,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $this->actingAs($salesA);

        $quotationIds = collect(app(QuotationService::class)->getForDataTable())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $orderIds = collect(app(OrderService::class)->getForDataTable())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $invoiceIds = collect(app(InvoiceService::class)->getForDataTable())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $receiptIds = collect(app(ReceiptService::class)->getForDataTable())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains($quotationA->id, $quotationIds);
        $this->assertContains($quotationB->id, $quotationIds);
        $this->assertContains($orderA->id, $orderIds);
        $this->assertContains($orderB->id, $orderIds);
        $this->assertContains($invoiceA->id, $invoiceIds);
        $this->assertContains($invoiceB->id, $invoiceIds);
        $this->assertContains($invoiceA->id, $receiptIds);
        $this->assertContains($invoiceB->id, $receiptIds);
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

    public function test_sales_cannot_see_quotation_when_it_was_created_by_another_user_even_if_enquiry_was_handled_by_them(): void
    {
        $salesA = User::factory()->create();
        $salesA->assignRole('sales');

        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $package = Package::create([
            'package_number' => 'PKG-CC-VIS-001',
            'name' => 'CC Visibility Package',
            'status' => 'open',
        ]);

        $group = $this->createConfirmationGroupHandledBy($salesA->id, $package->id, 'cc-visible');
        $customerId = $group->leader()?->customer_id;

        $this->assertNotNull($customerId);

        $quotation = Quotation::create([
            'customer_id' => (int) $customerId,
            'customer_confirmation_id' => $group->id,
            'created_by' => $salesB->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'draft',
            'description' => 'Quotation via CC visibility',
        ]);

        $quotationsForSalesA = app(QuotationService::class)->getForDataTable([
            'sales_id' => $salesA->id,
        ]);

        $this->assertCount(0, $quotationsForSalesA);
    }

    private function createConfirmationGroupHandledBy(
        int $handledBy,
        int $packageId,
        string $suffix,
        ?int $countryId = null,
        ?int $branchId = null,
    ): CustomerConfirmation {
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => "Handled {$suffix}",
            'contact_number' => "01234567{$suffix}",
            'email' => "handled-{$suffix}@test.com",
            'created_by' => $handledBy,
            'handled_by' => $handledBy,
            'country_id' => $countryId,
            'branch_id' => $branchId,
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
            'created_by' => $handledBy,
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
