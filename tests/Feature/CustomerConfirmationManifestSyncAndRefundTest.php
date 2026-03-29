<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerConfirmationManifestSyncAndRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_confirmation_update_syncs_open_manifest_member_only(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'sync-member@example.com',
            'contact' => '10000000',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-SYNC-001',
            'passport_number' => 'OLD-PASSPORT',
            'nationality' => 'Malaysia',
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => null,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
            'relationship' => 'Self',
        ]);

        $openPackage = Package::create([
            'package_number' => 'PKG-SYNC-OPEN',
            'name' => 'Open Package',
            'status' => 'open',
        ]);

        $closedPackage = Package::create([
            'package_number' => 'PKG-SYNC-CLOSE',
            'name' => 'Closed Package',
            'status' => 'closed',
        ]);

        $openManifest = Manifest::create([
            'package_id' => $openPackage->id,
            'manifest_number' => 'MNF-SYNC-OPEN',
        ]);

        $closedManifest = Manifest::create([
            'package_id' => $closedPackage->id,
            'manifest_number' => 'MNF-SYNC-CLOSED',
        ]);

        $openManifestMember = ManifestMember::create([
            'manifest_id' => $openManifest->id,
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Original Name',
            'passport_number' => 'OLD-PASSPORT',
            'sharing_plan' => 'single',
            'relationship' => 'Self',
        ]);

        $closedManifestMember = ManifestMember::create([
            'manifest_id' => $closedManifest->id,
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Original Name',
            'passport_number' => 'OLD-PASSPORT',
            'sharing_plan' => 'single',
            'relationship' => 'Self',
        ]);

        $response = $this->put(route('customer-confirmations.update', $group->id), [
            'date_of_application' => now()->format('Y-m-d'),
            'members' => [
                [
                    'member_id' => $member->id,
                    'customer_id' => $customer->id,
                    'is_leader' => true,
                    'name' => 'Updated Name',
                    'email' => 'sync-member@example.com',
                    'contact_number' => '19999999',
                    'nric_number' => 'S1234567A',
                    'address' => 'Updated Street',
                    'nationality' => 'Singapore',
                    'passport_number' => 'NEW-PASSPORT',
                    'passport_issue_date' => '2024-01-01',
                    'passport_expiry_date' => '2034-01-01',
                    'passport_place_of_issue' => 'Singapore',
                    'gender' => 'male',
                    'marital_status' => 'single',
                    'date_of_birth' => '1990-01-01',
                    'place_of_birth' => 'Singapore',
                    'first_time_umrah' => true,
                    'has_chronic_disease' => false,
                    'is_using_wheelchair' => false,
                    'chronic_disease_details' => null,
                    'status' => 'pending_payment',
                    'sharing_plan' => 'double',
                    'relationship' => 'Brother',
                ],
            ],
        ]);

        $response->assertRedirect();

        $openManifestMember->refresh();
        $closedManifestMember->refresh();

        $this->assertSame('Updated Name', (string) $openManifestMember->name);
        $this->assertSame('NEW-PASSPORT', (string) $openManifestMember->passport_number);
        $this->assertSame('double', (string) $openManifestMember->sharing_plan);
        $this->assertSame('Brother', (string) $openManifestMember->relationship);

        $this->assertSame('Original Name', (string) $closedManifestMember->name);
        $this->assertSame('OLD-PASSPORT', (string) $closedManifestMember->passport_number);
        $this->assertSame('single', (string) $closedManifestMember->sharing_plan);
        $this->assertSame('Self', (string) $closedManifestMember->relationship);
    }

    public function test_customer_confirmation_member_refund_creates_negative_receipt_with_linked_invoice(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Refund Member',
            'email' => 'refund-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-REFUND-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-REFUND-001',
            'name' => 'Refund Package',
            'status' => 'open',
            'price_single' => 1000,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-REFUND-001',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Refund Member',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $baseItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Base Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Base Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);

        $invoice->quotationItems()->sync([$baseItem->id]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->post(route('customer-confirmations.refunds.store', $group->id), [
            'member_refunds' => [
                [
                    'member_id' => $member->id,
                    'mode' => 'percentage',
                    'percentage' => 50,
                ],
            ],
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $invoice->id)
            ->where('amount', '-500.00')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundReceipt);

        $member->refresh();

        $this->assertNotSame('cancelled', $member->status);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex();
        $groupRow = collect($grouped)->firstWhere('id', $group->id);

        $this->assertNotNull($groupRow);
        $this->assertSame(500.0, (float) ($groupRow['paid_amount'] ?? 0));
    }

    public function test_generate_quotation_blocks_active_member_link_but_allows_after_cancellation(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Quoted Member',
            'email' => 'quoted-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-QUOTE-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-QUOTE-001',
            'name' => 'Quotation Package',
            'status' => 'open',
            'price_single' => 1200,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $activeQuotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'draft',
        ]);

        QuotationItem::create([
            'quotation_id' => $activeQuotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Existing active link',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1200,
            'sort_order' => 1,
        ]);

        $blockedResponse = $this->from(route('confirmed-customer.index'))
            ->post(route('customer-confirmations.generate-quotations', $group->id), [
                'payer_to_members' => [
                    $member->id => [$member->id],
                ],
            ]);

        $blockedResponse
            ->assertRedirect(route('confirmed-customer.index'))
            ->assertSessionHasErrors('payer_to_members');

        $activeQuotation->update(['status' => 'cancelled']);

        $allowedResponse = $this->post(route('customer-confirmations.generate-quotations', $group->id), [
            'payer_to_members' => [
                $member->id => [$member->id],
            ],
        ]);

        $allowedResponse->assertRedirect(route('quotation.index'));

        $this->assertDatabaseHas('quotation_items', [
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Quoted Member — Single Sharing',
        ]);
    }
}
