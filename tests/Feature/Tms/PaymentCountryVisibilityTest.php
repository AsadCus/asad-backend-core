<?php

namespace Tests\Feature\Tms;

use App\Models\Admin;
use App\Models\Country;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\Sales;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class PaymentCountryVisibilityTest extends TestCase
{
    public function test_payment_indexes_are_scoped_by_creator_country_and_global_records(): void
    {
        $countries = $this->createCountries();
        $fixtures = $this->createPaymentFixtures($countries['malaysia']->id, $countries['singapore']->id);

        $viewerMalaysia = $this->createScopedUser('sales', [$countries['malaysia']->id], [$countries['malaysia']->id]);
        $viewerSingapore = $this->createScopedUser('sales', [$countries['singapore']->id], [$countries['singapore']->id]);
        $viewerBoth = $this->createScopedUser('superadmin', [$countries['malaysia']->id, $countries['singapore']->id], [$countries['malaysia']->id, $countries['singapore']->id]);
        $viewerNoSelected = $this->createScopedUser('sales', [$countries['malaysia']->id, $countries['singapore']->id], []);

        $this->assertIndexVisibility($viewerMalaysia, ['MY', 'BOTH', 'GLOBAL']);
        $this->assertIndexVisibility($viewerSingapore, ['SG', 'BOTH', 'GLOBAL']);
        $this->assertIndexVisibility($viewerBoth, ['MY', 'SG', 'BOTH', 'GLOBAL']);
        $this->assertIndexVisibility($viewerNoSelected, ['MY', 'SG', 'BOTH', 'GLOBAL']);

        $this->assertSame(4, count($fixtures));
    }

    public function test_payment_api_show_endpoints_are_scoped_by_creator_country(): void
    {
        $countries = $this->createCountries();
        $fixtures = $this->createPaymentFixtures($countries['malaysia']->id, $countries['singapore']->id);

        $viewerMalaysia = $this->createScopedUser('sales', [$countries['malaysia']->id], [$countries['malaysia']->id]);

        $this->actingAs($viewerMalaysia)
            ->getJson(route('quotation.get-for-show', $fixtures['MY']['quotation']->id))
            ->assertOk()
            ->assertJsonPath('id', $fixtures['MY']['quotation']->id);

        $this->actingAs($viewerMalaysia)
            ->getJson(route('quotation.get-for-show', $fixtures['SG']['quotation']->id))
            ->assertNotFound();

        $this->actingAs($viewerMalaysia)
            ->getJson(route('invoice.get-for-show', $fixtures['BOTH']['invoice']->id))
            ->assertOk()
            ->assertJsonPath('id', $fixtures['BOTH']['invoice']->id);

        $this->actingAs($viewerMalaysia)
            ->getJson(route('invoice.get-for-show', $fixtures['SG']['invoice']->id))
            ->assertNotFound();

        $this->actingAs($viewerMalaysia)
            ->getJson(route('receipt.get-for-show', $fixtures['GLOBAL']['receipt']->id))
            ->assertOk()
            ->assertJsonPath('id', $fixtures['GLOBAL']['receipt']->id);

        $this->actingAs($viewerMalaysia)
            ->getJson(route('receipt.get-for-show', $fixtures['SG']['receipt']->id))
            ->assertNotFound();
    }

    public function test_dashboard_payment_summary_and_fytd_respect_creator_country_scope(): void
    {
        $countries = $this->createCountries();
        $this->createPaymentFixtures($countries['malaysia']->id, $countries['singapore']->id);

        $financialYear = FinancialYear::create([
            'year' => (string) now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_active' => true,
            'default' => true,
        ]);

        $viewerMalaysia = $this->createScopedUser('sales', [$countries['malaysia']->id], [$countries['malaysia']->id]);
        $viewerSingapore = $this->createScopedUser('sales', [$countries['singapore']->id], [$countries['singapore']->id]);
        $viewerNoSelected = $this->createScopedUser('sales', [$countries['malaysia']->id, $countries['singapore']->id], []);

        $routes = app('router')->getRoutes();

        if ($routes->getByName('dashboard.fiscal-year-sales') === null || $routes->getByName('dashboard.payment-report') === null) {
            $this->markTestSkipped('Dashboard fiscal-year or payment-summary routes are not registered.');
        }

        $this->actingAs($viewerMalaysia)
            ->getJson(route('dashboard.fiscal-year-sales', ['financial_year_id' => $financialYear->id]))
            ->assertOk()
            ->assertJsonPath('count', 3)
            ->assertJsonPath('amount', 800);

        $this->actingAs($viewerSingapore)
            ->getJson(route('dashboard.fiscal-year-sales', ['financial_year_id' => $financialYear->id]))
            ->assertOk()
            ->assertJsonPath('count', 3)
            ->assertJsonPath('amount', 900);

        $this->actingAs($viewerNoSelected)
            ->getJson(route('dashboard.fiscal-year-sales', ['financial_year_id' => $financialYear->id]))
            ->assertOk()
            ->assertJsonPath('count', 4)
            ->assertJsonPath('amount', 1000);

        $this->actingAs($viewerMalaysia)
            ->getJson(route('dashboard.payment-report', ['period' => 'daily']))
            ->assertOk()
            ->assertJsonPath('receipt_count', 3)
            ->assertJsonPath('total_amount', 800);

        $this->actingAs($viewerSingapore)
            ->getJson(route('dashboard.payment-report', ['period' => 'daily']))
            ->assertOk()
            ->assertJsonPath('receipt_count', 3)
            ->assertJsonPath('total_amount', 900);

        $this->actingAs($viewerNoSelected)
            ->getJson(route('dashboard.payment-report', ['period' => 'daily']))
            ->assertOk()
            ->assertJsonPath('receipt_count', 4)
            ->assertJsonPath('total_amount', 1000);
    }

    /**
     * @return array{malaysia: Country, singapore: Country}
     */
    private function createCountries(): array
    {
        return [
            'malaysia' => Country::factory()->create(['name' => 'Malaysia', 'adjective' => 'Malaysian']),
            'singapore' => Country::factory()->create(['name' => 'Singapore', 'adjective' => 'Singaporean']),
        ];
    }

    private function createScopedUser(string $role, array $assignableCountryIds, array $selectedCountryIds): User
    {
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');

        $user = User::factory()->create([
            'selected_country_ids' => $selectedCountryIds,
        ]);

        $user->assignRole($role);

        $payload = [
            'user_id' => $user->id,
            'country_id' => $assignableCountryIds[0] ?? null,
            'country_ids' => $assignableCountryIds,
            'branch_id' => null,
            'branch_ids' => [],
        ];

        if ($role === 'sales' || $role === 'admin') {
            Sales::create($payload);
        }

        if ($role === 'superadmin') {
            Admin::create($payload);
        }

        return $user;
    }

    /**
     * @return array<string, array{quotation: Quotation, order: Order, invoice: Invoice, receipt: Receipt}>
     */
    private function createPaymentFixtures(int $malaysiaCountryId, int $singaporeCountryId): array
    {
        $creatorMalaysia = $this->createScopedUser('sales', [$malaysiaCountryId], [$malaysiaCountryId]);
        $creatorSingapore = $this->createScopedUser('sales', [$singaporeCountryId], [$singaporeCountryId]);
        $creatorBoth = $this->createScopedUser('superadmin', [$malaysiaCountryId, $singaporeCountryId], [$malaysiaCountryId, $singaporeCountryId]);
        $creatorGlobal = $this->createScopedUser('sales', [], []);

        return [
            'MY' => $this->createPaymentGraph('MY', $creatorMalaysia, 100, $malaysiaCountryId),
            'SG' => $this->createPaymentGraph('SG', $creatorSingapore, 200, $singaporeCountryId),
            'BOTH' => $this->createPaymentGraph('BOTH', $creatorBoth, 300, null),
            'GLOBAL' => $this->createPaymentGraph('GLOBAL', $creatorGlobal, 400, null),
        ];
    }

    /**
     * @return array{quotation: Quotation, order: Order, invoice: Invoice, receipt: Receipt}
     */
    private function createPaymentGraph(string $suffix, User $creator, float $amount, ?int $countryId = null): array
    {
        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-'.$suffix,
        ]);

        $quotation = Quotation::create([
            'quotation_number' => 'Q-'.$suffix,
            'customer_id' => $customer->id,
            'handled_by' => $creator->id,
            'country_id' => $countryId,
            'quotation_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(7)->toDateString(),
            'payment_plan' => 'full',
            'status' => 'converted',
            'description' => 'Country scope '.$suffix,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'order_number' => 'O-'.$suffix,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'I-'.$suffix,
            'amount' => $amount,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'paid',
            'description' => 'Invoice '.$suffix,
            'payment_method' => 'cash',
        ]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'R-'.$suffix,
            'amount' => $amount,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'description' => 'Receipt '.$suffix,
        ]);

        return [
            'quotation' => $quotation,
            'order' => $order,
            'invoice' => $invoice,
            'receipt' => $receipt,
        ];
    }

    /**
     * @param  array<int, string>  $expectedSuffixes
     */
    private function assertIndexVisibility(User $viewer, array $expectedSuffixes): void
    {
        $expectedQuotationNumbers = collect($expectedSuffixes)->map(fn (string $suffix): string => 'Q-'.$suffix)->sort()->values()->all();
        $expectedOrderNumbers = collect($expectedSuffixes)->map(fn (string $suffix): string => 'O-'.$suffix)->sort()->values()->all();
        $expectedInvoiceNumbers = collect($expectedSuffixes)->map(fn (string $suffix): string => 'I-'.$suffix)->sort()->values()->all();
        $expectedReceiptNumbers = collect($expectedSuffixes)->map(fn (string $suffix): string => 'R-'.$suffix)->sort()->values()->all();

        $quotationResponse = $this->actingAs($viewer)->get(route('quotation.index'));
        $quotationResponse->assertOk();
        $quotationNumbers = collect($quotationResponse->viewData('page')['props']['data']['quotationsForDatatable'])
            ->pluck('quotation_number')
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expectedQuotationNumbers, $quotationNumbers);

        $orderResponse = $this->actingAs($viewer)->get(route('order.index'));
        $orderResponse->assertOk();
        $orderNumbers = collect($orderResponse->viewData('page')['props']['data']['ordersForDatatable'])
            ->pluck('order_number')
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expectedOrderNumbers, $orderNumbers);

        $invoiceResponse = $this->actingAs($viewer)->get(route('invoice.index'));
        $invoiceResponse->assertOk();
        $invoiceNumbers = collect($invoiceResponse->viewData('page')['props']['data']['invoicesForDatatable'])
            ->pluck('invoice_number')
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expectedInvoiceNumbers, $invoiceNumbers);

        $receiptResponse = $this->actingAs($viewer)->get(route('receipt.index'));
        $receiptResponse->assertOk();
        $receiptNumbers = collect($receiptResponse->viewData('page')['props']['data']['receiptsForDatatable'])
            ->pluck('receipt_number')
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expectedReceiptNumbers, $receiptNumbers);
    }
}
