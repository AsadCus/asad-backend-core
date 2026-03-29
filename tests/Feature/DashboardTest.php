<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $categories = collect($response->json('categories'));
        $matchingCategory = $categories->first(
            fn (array $category): bool => (float) ($category['amount'] ?? 0) === 550.0,
        );

        $this->assertNotNull($matchingCategory);
        $this->assertStringContainsString('Umrah Packages', (string) ($matchingCategory['category'] ?? ''));
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

    public function test_dashboard_payment_summary_includes_receipts_without_invoice_as_others(): void
    {
        $this->actingAs(User::factory()->create());

        Receipt::withoutEvents(function (): void {
            Receipt::create([
                'invoice_id' => null,
                'amount' => -125,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'refund',
                'description' => 'Refund - Test Member',
            ]);
        });

        $response = $this->getJson(route('dashboard.payment-summary-by-period', [
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
        $this->actingAs(User::factory()->create());

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

        $response = $this->getJson(route('dashboard.payment-summary-by-period', [
            'period' => 'daily',
        ]));

        $response->assertOk();
        $response->assertJsonPath('receipt_count', 0);
        $response->assertJsonPath('total_amount', 0);

        $quotation->update(['status' => 'expired']);

        $expiredResponse = $this->getJson(route('dashboard.payment-summary-by-period', [
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
        $this->actingAs(User::factory()->create());

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

        $response = $this->getJson(route('dashboard.payment-summary-by-period', [
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

    public function test_dashboard_payment_summary_report_blade_renders_summary_payload_without_groups(): void
    {
        $this->actingAs(User::factory()->create());

        $html = view('reports.dashboard-payment-summary', [
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
        $this->assertStringContainsString('Period', $html);
        $this->assertStringContainsString('Umrah Packages', $html);
        $this->assertStringContainsString('$1,200.00', $html);
        $this->assertStringContainsString('2 receipt rows', $html);
    }

    public function test_admin_dashboard_falls_back_when_default_financial_year_has_null_dates(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('customer', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

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
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('data.selectedYearId', $fallbackYear->id)
            ->where('data.fiscalYearStartDate', '2026-01-01')
        );
    }
}
