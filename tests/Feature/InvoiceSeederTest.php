<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Database\Seeders\InvoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_seeder_creates_one_invoice_for_full_payment_plan(): void
    {
        $order = $this->createOrderWithQuotationItems('full');

        $this->seed(InvoiceSeeder::class);

        $order->refresh();
        $invoices = $order->invoices()->with('quotationItems')->get();

        $this->assertCount(1, $invoices);
        $this->assertSame('Invoice For Full Payment', $invoices[0]->description);
        $this->assertGreaterThan(0, (float) $invoices[0]->amount);
        $this->assertCount(1, $invoices[0]->quotationItems);
    }

    public function test_invoice_seeder_creates_three_invoices_for_installment_with_split_member_items(): void
    {
        $order = $this->createOrderWithQuotationItems('installment');

        $this->seed(InvoiceSeeder::class);

        $order->refresh();

        $invoices = $order->invoices()
            ->with('quotationItems')
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $invoices);

        $descriptions = $invoices->pluck('description')->all();

        $this->assertSame([
            'Invoice For Deposit',
            'Invoice For 50%',
            'Invoice For Balance',
        ], $descriptions);

        $depositInvoice = $invoices->firstWhere('description', 'Invoice For Deposit');
        $fiftyInvoice = $invoices->firstWhere('description', 'Invoice For 50%');
        $balanceInvoice = $invoices->firstWhere('description', 'Invoice For Balance');

        $this->assertNotNull($depositInvoice);
        $this->assertNotNull($fiftyInvoice);
        $this->assertNotNull($balanceInvoice);

        $this->assertGreaterThan(0, (float) $depositInvoice->amount);
        $this->assertGreaterThan(0, (float) $fiftyInvoice->amount);
        $this->assertGreaterThan(0, (float) $balanceInvoice->amount);

        $this->assertTrue($depositInvoice->quotationItems->contains(function (QuotationItem $item): bool {
            return str_contains((string) $item->description, '(Deposit)')
                && (int) ($item->customer_confirmation_member_id ?? 0) > 0;
        }));

        $this->assertTrue($fiftyInvoice->quotationItems->contains(function (QuotationItem $item): bool {
            return str_contains((string) $item->description, '(50%)')
                && (int) ($item->customer_confirmation_member_id ?? 0) > 0;
        }));

        $this->assertTrue($balanceInvoice->quotationItems->contains(function (QuotationItem $item): bool {
            return str_contains((string) $item->description, '(Balance)')
                && (int) ($item->customer_confirmation_member_id ?? 0) > 0;
        }));
    }

    private function createOrderWithQuotationItems(string $paymentPlan): Order
    {
        $user = User::factory()->create();

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-INV-'.strtoupper($paymentPlan),
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->toDateString(),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(14)->toDateString(),
            'payment_plan' => $paymentPlan,
            'payment_method' => 'transfer',
            'status' => 'converted',
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package Cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        return Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => $paymentPlan,
        ]);
    }
}
