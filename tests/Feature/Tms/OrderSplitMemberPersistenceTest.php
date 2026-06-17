<?php

namespace Tests\Feature\Tms;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Tests\TmsTestCase as TestCase;

class OrderSplitMemberPersistenceTest extends TestCase
{
    public function test_order_store_keeps_member_id_for_split_items(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-ORD-SPLIT-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
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
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'accepted',
        ]);

        $response = $this->post(route('order.store'), [
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
            'invoices' => [
                [
                    '_key' => 'inv-deposit',
                    'description' => 'Invoice For Deposit',
                    'payment_method' => 'transfer',
                    'amount' => 3000,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->format('Y-m-d'),
                    'items' => [
                        [
                            '_key' => 'dep-item',
                            'id' => null,
                            'customer_confirmation_member_id' => $member->id,
                            'description' => 'Package Cost (Deposit)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 3000,
                            'sort_order' => 1,
                        ],
                    ],
                ],
                [
                    '_key' => 'inv-balance',
                    'description' => 'Invoice For Balance',
                    'payment_method' => 'transfer',
                    'amount' => 2000,
                    'invoice_date' => now()->addDay()->format('Y-m-d'),
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'items' => [
                        [
                            '_key' => 'bal-item',
                            'id' => null,
                            'customer_confirmation_member_id' => $member->id,
                            'description' => 'Package Cost (Balance)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 2000,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('invoice.index'));

        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'description' => 'Package Cost (Deposit)',
            'customer_confirmation_member_id' => $member->id,
        ]);

        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'description' => 'Package Cost (Balance)',
            'customer_confirmation_member_id' => $member->id,
        ]);

        $this->assertSame(2, Invoice::query()->count());
    }
}
