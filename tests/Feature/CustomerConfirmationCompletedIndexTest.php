<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerConfirmationCompletedIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_customer_index_classification_is_exclusive_across_all_menus(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Permission::findOrCreate('customer view', 'web');
        $user->givePermissionTo('customer view');

        $completedPackage = Package::create([
            'package_number' => 'PKG-COMPLETED-001',
            'name' => 'Completed Lifecycle Package',
            'status' => 'completed',
            'price_single' => 5000,
            'departure_date' => now()->subDay()->toDateString(),
        ]);

        $openPackage = Package::create([
            'package_number' => 'PKG-OPEN-001',
            'name' => 'Open Lifecycle Package',
            'status' => 'open',
            'price_single' => 5000,
        ]);

        $completedByPayment = CustomerConfirmation::create([
            'package_id' => $completedPackage->id,
            'is_holding' => false,
            'created_by' => $user->id,
        ]);

        $completedByCancellation = CustomerConfirmation::create([
            'package_id' => null,
            'is_holding' => true,
            'created_by' => $user->id,
        ]);

        $holdingActive = CustomerConfirmation::create([
            'package_id' => null,
            'is_holding' => true,
            'created_by' => $user->id,
        ]);

        $confirmedActive = CustomerConfirmation::create([
            'package_id' => $openPackage->id,
            'is_holding' => false,
            'created_by' => $user->id,
        ]);

        $completedPackageButPartial = CustomerConfirmation::create([
            'package_id' => $completedPackage->id,
            'is_holding' => false,
            'created_by' => $user->id,
        ]);

        $createMember = function (CustomerConfirmation $group, string $status, bool $isLeader = true): CustomerConfirmationMember {
            $memberUser = User::factory()->create();
            $customer = Customer::create([
                'user_id' => $memberUser->id,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $group->id,
                'customer_id' => $customer->id,
                'is_leader' => $isLeader,
                'status' => $status,
                'sharing_plan' => 'single',
            ]);
        };

        $completedPaidMember = $createMember($completedByPayment, 'fully_paid', true);

        $createMember($completedByCancellation, 'cancelled', true);

        $createMember($holdingActive, 'pending_payment', true);
        $createMember($confirmedActive, 'partially_paid', true);
        $createMember($completedPackageButPartial, 'partially_paid', true);

        $completedQuotation = Quotation::create([
            'customer_id' => $completedPaidMember->customer_id,
            'customer_confirmation_id' => $completedByPayment->id,
            'quotation_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $completedItem = QuotationItem::create([
            'quotation_id' => $completedQuotation->id,
            'customer_confirmation_member_id' => $completedPaidMember->id,
            'description' => 'Completed paid member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $completedOrder = Order::create([
            'quotation_id' => $completedQuotation->id,
            'order_number' => 'ORD-COMPLETED-001',
            'payment_plan' => 'full',
        ]);

        $completedInvoice = Invoice::create([
            'order_id' => $completedOrder->id,
            'description' => 'Completed paid invoice',
            'amount' => 5000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'paid',
        ]);

        $completedInvoice->quotationItems()->sync([$completedItem->id]);

        Receipt::create([
            'invoice_id' => $completedInvoice->id,
            'amount' => 5000,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'transfer',
        ]);

        $service = app(CustomerConfirmationService::class);

        $completedIds = collect($service->getForCompletedIndex())->pluck('id')->all();
        $completedGroupRow = collect($service->getForCompletedIndex())->firstWhere('id', $completedByPayment->id);
        $confirmedIds = collect($service->getForConfirmedIndex())->pluck('id')->all();
        $holdingIds = collect($service->getForHoldingIndex())->pluck('id')->all();

        $this->assertNotNull($completedGroupRow);

        $completedMemberRow = collect($completedGroupRow['members'] ?? [])->firstWhere('id', $completedPaidMember->id);

        $this->assertNotNull($completedMemberRow);
        $this->assertSame($completedOrder->id, $completedMemberRow['order_id']);
        $this->assertSame('ORD-COMPLETED-001', $completedMemberRow['order_number']);

        $this->assertContains($completedByPayment->id, $completedIds);
        $this->assertContains($completedByCancellation->id, $completedIds);
        $this->assertNotContains($holdingActive->id, $completedIds);
        $this->assertNotContains($confirmedActive->id, $completedIds);
        $this->assertNotContains($completedPackageButPartial->id, $completedIds);

        $this->assertNotContains($completedByPayment->id, $confirmedIds);
        $this->assertNotContains($completedByCancellation->id, $confirmedIds);
        $this->assertNotContains($holdingActive->id, $confirmedIds);
        $this->assertContains($confirmedActive->id, $confirmedIds);
        $this->assertContains($completedPackageButPartial->id, $confirmedIds);

        $this->assertNotContains($completedByPayment->id, $holdingIds);
        $this->assertNotContains($completedByCancellation->id, $holdingIds);
        $this->assertContains($holdingActive->id, $holdingIds);
        $this->assertNotContains($confirmedActive->id, $holdingIds);
        $this->assertNotContains($completedPackageButPartial->id, $holdingIds);

        $this->get(route('completed-customer.index'))
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->component('confirmed-customer/index')
                    ->where('pageTitle', 'Completed Customers')
            );
    }
}
