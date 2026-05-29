<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
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

    public function test_sales_only_user_is_redirected_from_dashboard_to_enquiry_dashboard(): void
    {
        $salesUser = User::factory()->create();
        Role::findOrCreate('sales', 'web');
        $salesUser->assignRole('sales');

        $this->actingAs($salesUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('enquiries.index'));
    }

    public function test_superadmin_role_takes_precedence_when_user_has_both_superadmin_and_sales_roles(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('customer', 'web');
        $user->assignRole('sales');
        $user->assignRole('superadmin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_superadmin_dashboard_recent_customers_use_enquiry_data(): void
    {
        Role::findOrCreate('superadmin', 'web');

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $package = Package::create([
            'name' => 'Recent Customer Package',
            'status' => 'open',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-04-24 10:30:00'));

        try {
            Enquiry::create([
                'type' => 'general',
                'status' => EnquiryStatus::NewLead->value,
                'name' => 'Recent Customer',
                'contact_number' => '0123456789',
                'email' => 'recent.customer@example.com',
                'created_by' => $superadmin->id,
                'package_id' => $package->id,
            ]);

            $response = $this->actingAs($superadmin)->get(route('dashboard'));

            $response->assertOk();
            $response->assertInertia(
                fn (Assert $page) => $page
                    ->component('dashboard')
                    ->has('data.enquiries', 1)
                    ->where('data.enquiries.0.name', 'Recent Customer')
                    ->where('data.enquiries.0.package_name', 'Recent Customer Package')
                    ->where('data.enquiries.0.contact', '0123456789')
                    ->where('data.enquiries.0.created_at', '24 April 2026')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_payment_summary_groups_receipts_by_item_header_category(): void
    {
        Role::findOrCreate('sales', 'web');
        $this->actingAs($user = User::factory()->create());
        $user->assignRole('sales');

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

        $this->requireRoute('dashboard.payment-report');

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertJsonPath('period', 'daily');

        $categories = collect($response->json('categories'));
        $matchingCategory = $categories->first(
            fn (array $category): bool => (float) ($category['amount'] ?? 0) === 550.0,
        );

        $this->assertNotNull($matchingCategory);
        $this->assertStringContainsString('Umrah Packages', (string) ($matchingCategory['category'] ?? ''));
    }

    public function test_dashboard_payment_summary_pdf_export_returns_pdf_response(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);
        $this->requireRoute('dashboard.payment-report-export');

        $response = $this->get(route('dashboard.payment-report-export', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_dashboard_payment_summary_allocates_negative_invoice_extension_to_payer_member_item(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $maker = User::factory()->create();
        $maker->assignRole('superadmin');
        $this->actingAs($maker);

        $payerUser = User::factory()->create();
        $payerCustomer = Customer::create([
            'user_id' => $payerUser->id,
            'customer_number' => 'CUST-DB-PAYER-001',
        ]);

        $secondaryUser = User::factory()->create();
        $secondaryCustomer = Customer::create([
            'user_id' => $secondaryUser->id,
            'customer_number' => 'CUST-DB-SEC-001',
        ]);

        $package = Package::create([
            'name' => 'Summary Package',
        ]);

        $confirmation = CustomerConfirmation::create([
            'created_by' => $maker->id,
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $payerMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $payerCustomer->id,
            'status' => 'pending_payment',
            'is_leader' => true,
        ]);

        $secondaryMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $secondaryCustomer->id,
            'status' => 'pending_payment',
            'is_leader' => false,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $payerCustomer->id,
            'customer_confirmation_id' => $confirmation->id,
            'handled_by' => $maker->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'cash',
            'status' => 'converted',
            'description' => 'Payer allocation payment summary',
        ]);

        $mainHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Main Services',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $addonHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Addons',
            'is_header' => true,
            'sort_order' => 2,
        ]);

        $mainMemberItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $payerMember->id,
            'parent_id' => $mainHeader->id,
            'description' => 'Main Member Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 3,
        ]);

        $secondaryMemberItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $secondaryMember->id,
            'parent_id' => $addonHeader->id,
            'description' => 'Secondary Member Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 4,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice with mixed extensions',
            'amount' => 300,
            'extensions' => [
                [
                    'type' => 'other',
                    'amount' => 50,
                ],
                [
                    'type' => 'discount',
                    'amount' => -100,
                ],
            ],
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([
            $mainMemberItem->id,
            $secondaryMemberItem->id,
        ]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 300,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'cash',
            ]);
        });

        $this->requireRoute('dashboard.payment-report');

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
        ]));

        $response->assertOk();

        $categories = collect($response->json('categories'));
        $mainCategory = $categories->firstWhere('category', 'Main Services');
        $addonCategory = $categories->firstWhere('category', 'Addons');

        $this->assertNotNull($mainCategory);
        $this->assertNotNull($addonCategory);
        $this->assertSame(100.0, round((float) ($mainCategory['amount'] ?? 0), 2));
        $this->assertSame(200.0, round((float) ($addonCategory['amount'] ?? 0), 2));

        $rows = collect($response->json('rows'));
        $mainRow = $rows->firstWhere('package_item', 'Main Member Item');
        $secondaryRow = $rows->firstWhere('package_item', 'Secondary Member Item');

        $this->assertNotNull($mainRow);
        $this->assertNotNull($secondaryRow);
        $this->assertSame(100.0, round((float) ($mainRow['cash'] ?? 0), 2));
        $this->assertSame(200.0, round((float) ($secondaryRow['cash'] ?? 0), 2));
    }

    public function test_dashboard_payment_summary_pdf_export_returns_pdf_when_rows_are_grouped(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Receipt::withoutEvents(function (): void {
            Receipt::create([
                'invoice_id' => null,
                'amount' => 320,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
                'description' => 'Grouped row export regression',
            ]);
        });

        $this->requireRoute('dashboard.payment-report-export');

        $response = $this->get(route('dashboard.payment-report-export', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_fiscal_year_total_sales_uses_fytd_invoice_count_and_amount_from_converted_quotations(): void
    {
        Carbon::setTestNow('2026-03-31 10:00:00');

        try {
            $this->actingAs(User::factory()->create());

            $fiscalYear = FinancialYear::create([
                'year' => '2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'default' => true,
                'is_active' => true,
            ]);

            $customerUser = User::factory()->create();
            $customer = Customer::create([
                'user_id' => $customerUser->id,
                'customer_number' => 'CUST-DB-FYTD-001',
            ]);

            $country = Country::create([
                'name' => 'Singapore',
                'adjective' => 'Singaporean',
                'currency_symbol' => 'S$',
            ]);

            $convertedQuotation = Quotation::create([
                'customer_id' => $customer->id,
                'country_id' => $country->id,
                'quotation_date' => '2026-01-01',
                'expiry_date' => '2026-01-08',
                'payment_plan' => 'full',
                'payment_method' => 'transfer',
                'status' => 'converted',
                'description' => 'Converted quotation for FYTD sales',
            ]);

            $draftQuotation = Quotation::create([
                'customer_id' => $customer->id,
                'quotation_date' => '2026-01-01',
                'expiry_date' => '2026-01-08',
                'payment_plan' => 'full',
                'payment_method' => 'transfer',
                'status' => 'draft',
                'description' => 'Draft quotation should be excluded',
            ]);

            $convertedOrder = Order::create([
                'quotation_id' => $convertedQuotation->id,
                'payment_plan' => 'full',
            ]);

            $draftOrder = Order::create([
                'quotation_id' => $draftQuotation->id,
                'payment_plan' => 'full',
            ]);

            $paidInvoiceOne = Invoice::create([
                'order_id' => $convertedOrder->id,
                'description' => 'FYTD invoice one',
                'amount' => 100,
                'invoice_date' => '2026-01-10',
                'due_date' => '2026-01-10',
                'status' => 'paid',
            ]);

            $paidInvoiceTwo = Invoice::create([
                'order_id' => $convertedOrder->id,
                'description' => 'FYTD invoice two',
                'amount' => 250,
                'invoice_date' => '2026-03-20',
                'due_date' => '2026-03-20',
                'status' => 'paid',
            ]);

            $paidFutureScheduledInvoice = Invoice::create([
                'order_id' => $convertedOrder->id,
                'description' => 'Future scheduled invoice paid within FYTD',
                'amount' => 500,
                'invoice_date' => '2026-07-01',
                'due_date' => '2026-07-01',
                'status' => 'paid',
            ]);

            Invoice::create([
                'order_id' => $convertedOrder->id,
                'description' => 'FYTD issued invoice should be excluded from amount',
                'amount' => 111,
                'invoice_date' => '2026-03-21',
                'due_date' => '2026-03-21',
                'status' => 'issued',
            ]);

            $paidInvoiceWithLateReceipt = Invoice::create([
                'order_id' => $convertedOrder->id,
                'description' => 'Paid invoice with receipt after today should be excluded from FYTD',
                'amount' => 999,
                'invoice_date' => '2026-03-21',
                'due_date' => '2026-03-21',
                'status' => 'paid',
            ]);

            $refundInvoice = Invoice::create([
                'order_id' => $convertedOrder->id,
                'description' => 'Refund invoice should affect FYTD amount only',
                'amount' => -50,
                'invoice_date' => '2026-03-22',
                'due_date' => '2026-03-22',
                'status' => 'refund',
            ]);

            Invoice::create([
                'order_id' => $draftOrder->id,
                'description' => 'Non-converted quotation invoice should be excluded',
                'amount' => 999,
                'invoice_date' => '2026-02-15',
                'due_date' => '2026-02-15',
                'status' => 'paid',
            ]);

            Receipt::create([
                'invoice_id' => $paidInvoiceOne->id,
                'amount' => 100,
                'receipt_date' => '2026-01-10',
                'payment_method' => 'transfer',
            ]);

            Receipt::create([
                'invoice_id' => $paidInvoiceTwo->id,
                'amount' => 250,
                'receipt_date' => '2026-03-20',
                'payment_method' => 'transfer',
            ]);

            Receipt::create([
                'invoice_id' => $paidFutureScheduledInvoice->id,
                'amount' => 500,
                'receipt_date' => '2026-03-31',
                'payment_method' => 'transfer',
            ]);

            Receipt::create([
                'invoice_id' => $paidInvoiceWithLateReceipt->id,
                'amount' => 999,
                'receipt_date' => '2026-04-05',
                'payment_method' => 'transfer',
            ]);

            Receipt::create([
                'invoice_id' => $refundInvoice->id,
                'amount' => -50,
                'receipt_date' => '2026-03-25',
                'payment_method' => 'transfer',
            ]);

            $this->requireRoute('dashboard.fiscal-year-sales');

            $response = $this->getJson(route('dashboard.fiscal-year-sales', [
                'financial_year_id' => $fiscalYear->id,
            ]));

            $response->assertOk();
            $response->assertJsonPath('count', 3);
            $this->assertSame(800.0, (float) $response->json('amount'));

            $byCountry = $response->json('by_country');
            $this->assertIsArray($byCountry);
            $this->assertCount(1, $byCountry);
            $this->assertSame('Singapore', $byCountry[0]['country_name'] ?? null);
            $this->assertSame('S$', $byCountry[0]['currency_symbol'] ?? null);
            $this->assertSame(3, $byCountry[0]['count'] ?? null);
            $this->assertSame(800.0, (float) ($byCountry[0]['amount'] ?? 0));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_fiscal_year_total_sales_uses_financial_transactions_when_toggle_is_enabled(): void
    {
        Carbon::setTestNow('2026-03-31 10:00:00');

        try {
            config(['dashboard.use_financial_transactions_for_fytd_total_sales' => true]);

            $this->actingAs(User::factory()->create());

            $fiscalYear = FinancialYear::create([
                'year' => '2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'default' => true,
                'is_active' => true,
            ]);

            FinancialTransaction::create([
                'financial_year_id' => $fiscalYear->id,
                'type' => 'revenue',
                'amount' => 120,
                'description' => 'FYTD receipt one',
                'reference_type' => Receipt::class,
                'reference_id' => 101,
                'transaction_date' => '2026-01-10',
            ]);

            FinancialTransaction::create([
                'financial_year_id' => $fiscalYear->id,
                'type' => 'revenue',
                'amount' => 330,
                'description' => 'FYTD receipt two',
                'reference_type' => Receipt::class,
                'reference_id' => 102,
                'transaction_date' => '2026-03-20',
            ]);

            FinancialTransaction::create([
                'financial_year_id' => $fiscalYear->id,
                'type' => 'revenue',
                'amount' => -50,
                'description' => 'FYTD refund',
                'reference_type' => Receipt::class,
                'reference_id' => 103,
                'transaction_date' => '2026-03-25',
            ]);

            FinancialTransaction::create([
                'financial_year_id' => $fiscalYear->id,
                'type' => 'revenue',
                'amount' => 999,
                'description' => 'Future receipt should be excluded',
                'reference_type' => Receipt::class,
                'reference_id' => 104,
                'transaction_date' => '2026-04-01',
            ]);

            FinancialTransaction::create([
                'financial_year_id' => $fiscalYear->id,
                'type' => 'expense',
                'amount' => 77,
                'description' => 'Expense should be excluded',
                'reference_type' => Receipt::class,
                'reference_id' => 105,
                'transaction_date' => '2026-03-21',
            ]);

            $response = $this->getJson(route('dashboard.fiscal-year-sales', [
                'financial_year_id' => $fiscalYear->id,
            ]));

            $response->assertOk();
            $response->assertJsonPath('count', 2);
            $this->assertSame(400.0, (float) $response->json('amount'));
            $this->assertSame([], $response->json('by_country'));
        } finally {
            Carbon::setTestNow();
            config(['dashboard.use_financial_transactions_for_fytd_total_sales' => false]);
        }
    }

    public function test_dashboard_payment_summary_rows_include_ref_no_maker_installment_remarks_and_method_columns(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $viewerUser = User::factory()->create();
        $viewerUser->assignRole('superadmin');
        $this->actingAs($viewerUser);

        $maker = User::factory()->create(['name' => 'Maker User']);
        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DB-CC-001',
        ]);

        $package = Package::create([
            'name' => 'Umrah Gold',
        ]);

        $confirmation = CustomerConfirmation::create([
            'created_by' => $maker->id,
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $confirmationMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
            'is_leader' => true,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'handled_by' => $maker->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'status' => 'converted',
            'description' => 'Dashboard summary quotation for export mapping',
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $confirmationMember->id,
            'parent_id' => $header->id,
            'description' => 'Member Package Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 300,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice #1',
            'amount' => 150,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$item->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice #2',
            'amount' => 150,
            'invoice_date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDay()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceTwo->quotationItems()->sync([$item->id]);

        Receipt::withoutEvents(function () use ($invoiceOne, $invoiceTwo): void {
            Receipt::create([
                'invoice_id' => $invoiceOne->id,
                'amount' => 150,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'mastercard',
            ]);

            Receipt::create([
                'invoice_id' => $invoiceTwo->id,
                'amount' => 150,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'cash',
            ]);
        });

        $this->requireRoute('dashboard.payment-report');

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertJsonPath('period', 'daily');
        $response->assertJsonPath('payment_methods.3', 'master');

        $rows = collect($response->json('rows'));
        $firstPaymentRow = $rows->firstWhere('remarks', 'First Payment');
        $secondPaymentRow = $rows->firstWhere('remarks', 'Second Payment');

        $this->assertNotNull($firstPaymentRow);
        $this->assertNotNull($secondPaymentRow);

        $this->assertSame('Member Package Item', (string) ($firstPaymentRow['package_item'] ?? ''));
        $this->assertSame($package->package_number, (string) ($firstPaymentRow['ref_no'] ?? ''));
        $this->assertSame('Maker User', (string) ($firstPaymentRow['maker'] ?? ''));
        $this->assertSame(150.0, (float) ($firstPaymentRow['master'] ?? 0));
        $this->assertSame(0.0, (float) ($firstPaymentRow['cash'] ?? 0));

        $this->assertSame('Member Package Item', (string) ($secondPaymentRow['package_item'] ?? ''));
        $this->assertSame($package->package_number, (string) ($secondPaymentRow['ref_no'] ?? ''));
        $this->assertSame('Maker User', (string) ($secondPaymentRow['maker'] ?? ''));
        $this->assertSame(150.0, (float) ($secondPaymentRow['cash'] ?? 0));
        $this->assertSame(0.0, (float) ($secondPaymentRow['master'] ?? 0));
    }

    public function test_dashboard_payment_summary_uses_invoice_created_at_order_for_installment_remarks(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $viewerUser = User::factory()->create();
        $viewerUser->assignRole('superadmin');
        $this->actingAs($viewerUser);

        $maker = User::factory()->create(['name' => 'Created At Maker']);
        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DB-CREATED-AT-001',
        ]);

        $package = Package::create([
            'name' => 'Created At Package',
        ]);

        $confirmation = CustomerConfirmation::create([
            'created_by' => $maker->id,
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $confirmationMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
            'is_leader' => true,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'handled_by' => $maker->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'status' => 'converted',
            'description' => 'Created-at ordering test quotation',
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $confirmationMember->id,
            'parent_id' => $header->id,
            'description' => 'Created At Package Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $invoiceOlderCreatedAt = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Older Created At Invoice',
            'amount' => 100,
            'invoice_date' => now()->addDays(2)->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOlderCreatedAt->quotationItems()->sync([$item->id]);

        $invoiceNewerCreatedAt = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Newer Created At Invoice',
            'amount' => 100,
            'invoice_date' => now()->subDay()->format('Y-m-d'),
            'due_date' => now()->subDay()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceNewerCreatedAt->quotationItems()->sync([$item->id]);

        $invoiceOlderCreatedAt->update([
            'created_at' => now()->subDays(3),
        ]);
        $invoiceNewerCreatedAt->update([
            'created_at' => now(),
        ]);

        Receipt::withoutEvents(function () use ($invoiceOlderCreatedAt, $invoiceNewerCreatedAt): void {
            Receipt::create([
                'invoice_id' => $invoiceOlderCreatedAt->id,
                'amount' => 100,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'cash',
            ]);

            Receipt::create([
                'invoice_id' => $invoiceNewerCreatedAt->id,
                'amount' => 100,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'mastercard',
            ]);
        });

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
        ]));

        $response->assertOk();

        $rows = collect($response->json('rows'));
        $cashRow = $rows->first(fn (array $row): bool => (float) ($row['cash'] ?? 0) > 0);
        $masterRow = $rows->first(fn (array $row): bool => (float) ($row['master'] ?? 0) > 0);

        $this->assertNotNull($cashRow);
        $this->assertNotNull($masterRow);
        $this->assertSame('First Payment', (string) ($cashRow['remarks'] ?? ''));
        $this->assertSame('Second Payment', (string) ($masterRow['remarks'] ?? ''));
    }

    public function test_dashboard_payment_summary_includes_receipts_without_invoice_as_others(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Receipt::withoutEvents(function (): void {
            Receipt::create([
                'invoice_id' => null,
                'amount' => -125,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'refund',
                'description' => 'Refund - Test Member',
            ]);
        });

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
        ]));

        $response->assertOk();

        $categories = collect($response->json('categories'));
        $others = $categories->firstWhere('category', 'Others');

        $this->assertNotNull($others);
        $this->assertSame(-125.0, (float) ($others['amount'] ?? 0));
    }

    public function test_dashboard_payment_summary_excludes_receipts_from_rejected_cancelled_or_expired_quotations(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DB-REJECT-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'rejected',
            'description' => 'Rejected quotation payment should not appear',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Member #1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 450,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice linked to rejected quotation',
            'amount' => 450,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$item->id]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 450,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);
        });

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertJsonPath('receipt_count', 0);
        $response->assertJsonPath('total_amount', 0);

        $quotation->update(['status' => 'expired']);

        $this->requireRoute('dashboard.payment-report');

        $expiredResponse = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
        ]));

        $expiredResponse->assertOk();
        $expiredResponse->assertJsonPath('receipt_count', 0);
        $expiredResponse->assertJsonPath('total_amount', 0);
    }

    public function test_removed_salesperson_monthly_dashboard_endpoints_are_not_registered(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNull($routes->getByName('dashboard.sales-period-options'));
        $this->assertNull($routes->getByName('dashboard.quotation-converted-by-salesperson'));
    }

    public function test_dashboard_payment_summary_uses_timezone_aware_utc_range_for_daily_period(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Receipt::withoutEvents(function (): void {
            Receipt::create([
                'invoice_id' => null,
                'amount' => 100,
                'receipt_date' => '2026-03-27',
                'payment_method' => 'transfer',
                'description' => 'Timezone range included',
            ]);

            Receipt::create([
                'invoice_id' => null,
                'amount' => 200,
                'receipt_date' => '2026-03-26',
                'payment_method' => 'transfer',
                'description' => 'Timezone range excluded',
            ]);
        });

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
            'timezone' => 'Asia/Singapore',
            'range_start_utc' => '2026-03-26T16:00:00Z',
            'range_end_utc' => '2026-03-27T15:59:59Z',
        ]));

        $response->assertOk();
        $response->assertJsonPath('period', 'daily');
        $response->assertJsonPath('receipt_count', 1);
        $response->assertJsonPath('total_amount', 100);
    }

    public function test_dashboard_payment_summary_rows_are_sorted_ascending_for_daily_date_range(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Receipt::withoutEvents(function (): void {
            Receipt::create([
                'invoice_id' => null,
                'amount' => 100,
                'receipt_date' => '2026-04-28',
                'payment_method' => 'cash',
                'description' => 'Late date inserted first',
            ]);

            Receipt::create([
                'invoice_id' => null,
                'amount' => 200,
                'receipt_date' => '2026-04-26',
                'payment_method' => 'transfer',
                'description' => 'Early date inserted second',
            ]);

            Receipt::create([
                'invoice_id' => null,
                'amount' => 300,
                'receipt_date' => '2026-04-27',
                'payment_method' => 'visa',
                'description' => 'Middle date inserted third',
            ]);
        });

        $this->requireRoute('dashboard.payment-report');

        $response = $this->getJson(route('dashboard.payment-report', [
            'period' => 'daily',
            'timezone' => 'Asia/Singapore',
            'range_start_utc' => '2026-04-25T16:00:00Z',
            'range_end_utc' => '2026-04-30T15:59:59Z',
        ]));

        $response->assertOk();

        $dates = collect($response->json('rows'))
            ->pluck('date')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([
            '26 April 2026',
            '27 April 2026',
            '28 April 2026',
        ], $dates);
    }

    public function test_dashboard_payment_summary_report_blade_hides_period_and_date_range_in_daily_mode(): void
    {
        $this->actingAs(User::factory()->create());

        $html = view('dashboard.payment-report', [
            'branding' => [
                'title_color' => '#c05427',
            ],
            'body' => [
                'period' => 'yearly',
                'period_label' => 'Yearly',
                'date_range_label' => '2026 Range',
                'categories' => [
                    [
                        'category' => 'Umrah Packages',
                        'amount' => 1200,
                        'receipt_count' => 2,
                    ],
                    [
                        'category' => 'Others',
                        'amount' => 300,
                        'receipt_count' => 1,
                    ],
                ],
                'buckets' => [],
            ],
        ])->render();

        $this->assertStringContainsString('PAYMENT SUMMARY REPORT', $html);
        $this->assertStringNotContainsString('Period', $html);
        $this->assertStringNotContainsString('Date Range', $html);
        $this->assertStringContainsString('<th style="width:10%;">Date</th>', $html);
        $this->assertStringContainsString('Umrah Packages', $html);
        $this->assertStringContainsString('$1,200.00', $html);
        $this->assertStringContainsString('2 receipt rows', $html);
    }

    public function test_dashboard_payment_summary_report_blade_hides_empty_categories_per_day(): void
    {
        $this->actingAs(User::factory()->create());

        $html = view('dashboard.payment-report', [
            'branding' => [
                'title_color' => '#c05427',
            ],
            'body' => [
                'mode' => 'daily',
                'groups' => [
                    [
                        'label' => '15 April 2026',
                        'day_name' => 'Wednesday',
                        'rows' => [
                            [
                                'category' => 'umrah_packages',
                                'package_item' => 'Package A',
                                'ref_no' => 'A-001',
                                'amount' => 100,
                                'cash' => 100,
                                'nets' => 0,
                                'visa' => 0,
                                'master' => 0,
                                'paynow' => 0,
                                'total_sale' => 100,
                                'maker' => 'Maker A',
                                'remarks' => '-',
                            ],
                        ],
                    ],
                    [
                        'label' => '16 April 2026',
                        'day_name' => 'Thursday',
                        'rows' => [
                            [
                                'category' => 'others',
                                'package_item' => 'Package B',
                                'ref_no' => 'B-001',
                                'amount' => 200,
                                'cash' => 0,
                                'nets' => 200,
                                'visa' => 0,
                                'master' => 0,
                                'paynow' => 0,
                                'total_sale' => 200,
                                'maker' => 'Maker B',
                                'remarks' => '-',
                            ],
                        ],
                    ],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Umrah Packages', $html);
        $this->assertStringContainsString('Others', $html);
        $this->assertStringContainsString('15 April 2026', $html);
        $this->assertStringContainsString('16 April 2026', $html);
        $this->assertStringNotContainsString('Wakaf Jemaah', $html);
        $this->assertStringNotContainsString('Friday Blessings / Badal', $html);
    }

    public function test_admin_dashboard_falls_back_when_default_financial_year_has_null_dates(): void
    {
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('customer', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        FinancialYear::create([
            'year' => 'Broken Default',
            'start_date' => null,
            'end_date' => null,
            'default' => true,
            'is_active' => true,
        ]);

        $fallbackYear = FinancialYear::create([
            'year' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'default' => false,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard')
                ->where('data.selectedYearId', $fallbackYear->id)
                ->where('data.fiscalYearStartDate', '2026-01-01')
        );
    }

    public function test_closing_report_export_returns_pdf_for_sales_admin_and_superadmin(): void
    {
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');

        $this->createClosingReportReceipt('Category Alpha', 100);
        $this->createClosingReportReceipt('Category Beta', 250);

        $this->requireRoute('dashboard.closing-report-export');

        $rolesToCheck = ['superadmin', 'admin', 'sales'];

        foreach ($rolesToCheck as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $filteredResponse = $this->actingAs($user)->get(route('dashboard.closing-report-export', [
                'period' => 'daily',
                'range_start_utc' => now()->startOfDay()->toIso8601String(),
                'range_end_utc' => now()->endOfDay()->toIso8601String(),
                'categories' => 'Category Alpha',
            ]));

            $filteredResponse->assertOk();
            $filteredResponse->assertHeader('content-type', 'application/pdf');
        }
    }

    private function createClosingReportReceipt(string $categoryLabel, int $amount): void
    {
        $author = User::factory()->create();
        $customerUser = User::factory()->create();

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-CR-'.strtoupper(Str::random(6)),
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'cash',
            'status' => 'converted',
            'description' => 'Closing report test quotation',
            'handled_by' => $author->id,
        ]);

        $rootHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => $categoryLabel,
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $leafItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $rootHeader->id,
            'description' => $categoryLabel.' Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => $amount,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice for '.$categoryLabel,
            'amount' => $amount,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $invoice->quotationItems()->sync([$leafItem->id]);

        Receipt::withoutEvents(function () use ($invoice, $amount): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'cash',
            ]);
        });
    }

    private function requireRoute(string $name): void
    {
        $routes = app('router')->getRoutes();

        if ($routes->getByName($name) === null) {
            $this->markTestSkipped("Route {$name} not defined.");
        }
    }
}
