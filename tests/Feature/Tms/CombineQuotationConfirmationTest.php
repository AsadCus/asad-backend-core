<?php

namespace Tests\Feature\Tms;

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
use App\Services\PaymentStatusService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TmsTestCase as TestCase;

class CombineQuotationConfirmationTest extends TestCase
{
    protected function tearDown(): void
    {
        // The combine engine toggles this static; never leak it across tests.
        PaymentStatusService::$suppressManifestAutoLink = false;

        parent::tearDown();
    }

    private function service(): CustomerConfirmationService
    {
        return app(CustomerConfirmationService::class);
    }

    private function makePackage(): Package
    {
        return Package::create([
            'name' => 'Test Umrah Package',
            'status' => 'active',
            'price_single' => 3000,
            'price_double' => 3000,
            'price_triple' => 3000,
            'price_quad' => 3000,
        ]);
    }

    private function makeMember(CustomerConfirmation $cc, string $number, bool $isLeader): CustomerConfirmationMember
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => $number,
        ]);

        return CustomerConfirmationMember::create([
            'customer_confirmation_id' => $cc->id,
            'customer_id' => $customer->id,
            'is_leader' => $isLeader,
            'status' => 'pending_payment',
            'sharing_plan' => 'double',
        ]);
    }

    /**
     * Build a quotation (with header, member items, order, invoice, paid receipt)
     * for the given members. Each member item is rated at 3000.
     *
     * @param  array<int, CustomerConfirmationMember>  $members
     */
    private function makeQuotation(
        CustomerConfirmation $cc,
        CustomerConfirmationMember $payer,
        array $members,
    ): Quotation {
        $quotation = Quotation::create([
            'customer_id' => $payer->customer_id,
            'customer_confirmation_id' => $cc->id,
            'handled_by' => auth()->id(),
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'accepted',
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => null,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $itemIds = [];
        $sortOrder = 2;
        foreach ($members as $member) {
            $item = QuotationItem::create([
                'quotation_id' => $quotation->id,
                'customer_confirmation_member_id' => $member->id,
                'parent_id' => $header->id,
                'description' => 'Member item',
                'is_header' => false,
                'quantity' => 1,
                'rate' => 3000,
                'sort_order' => $sortOrder++,
            ]);
            $itemIds[] = (int) $item->id;
        }

        $amount = 3000 * count($members);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice',
            'amount' => $amount,
            'invoice_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync($itemIds);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        return $quotation->fresh();
    }

    private function totalReceiptAmount(): float
    {
        return round((float) Receipt::query()->sum('amount'), 2);
    }

    private function totalInvoiceAmount(): float
    {
        return round((float) Invoice::query()->sum('amount'), 2);
    }

    public function test_combine_quotations_merges_all_members_and_preserves_payments(): void
    {
        PaymentStatusService::$suppressManifestAutoLink = true;
        $this->actingAs(User::factory()->create());

        $package = $this->makePackage();
        $cc = CustomerConfirmation::create(['package_id' => $package->id]);

        $m1 = $this->makeMember($cc, 'C-1', true);
        $m2 = $this->makeMember($cc, 'C-2', false);
        $m3 = $this->makeMember($cc, 'C-3', false);

        $target = $this->makeQuotation($cc, $m1, [$m1, $m2]);
        $source = $this->makeQuotation($cc, $m3, [$m3]);

        $totalReceipts = $this->totalReceiptAmount();
        $totalInvoices = $this->totalInvoiceAmount();

        $this->service()->combineQuotations((int) $cc->id, (int) $target->id, [(int) $m3->id]);

        // Source quotation + its order are removed.
        $this->assertNull(Quotation::find($source->id), 'Emptied source quotation should be deleted.');
        $this->assertFalse(Order::where('quotation_id', $source->id)->exists());

        // Member 3 now sits under the target quotation.
        $m3Item = QuotationItem::where('customer_confirmation_member_id', $m3->id)
            ->where('is_header', false)
            ->first();
        $this->assertNotNull($m3Item);
        $this->assertSame((int) $target->id, (int) $m3Item->quotation_id);

        // Target quotation now covers all three members.
        $targetMemberCount = QuotationItem::where('quotation_id', $target->id)
            ->where('is_header', false)
            ->whereNotNull('customer_confirmation_member_id')
            ->distinct('customer_confirmation_member_id')
            ->count('customer_confirmation_member_id');
        $this->assertSame(3, $targetMemberCount);

        // No payment data lost or duplicated.
        $this->assertSame($totalReceipts, $this->totalReceiptAmount());
        $this->assertSame($totalInvoices, $this->totalInvoiceAmount());

        // No members lost; paid member retained as fully paid.
        $this->assertSame(3, CustomerConfirmationMember::where('customer_confirmation_id', $cc->id)
            ->where('status', '!=', 'cancelled')->count());
        $this->assertSame('fully_paid', $m3->fresh()->status);
    }

    public function test_combine_quotations_partial_keeps_source_alive(): void
    {
        PaymentStatusService::$suppressManifestAutoLink = true;
        $this->actingAs(User::factory()->create());

        $package = $this->makePackage();
        $cc = CustomerConfirmation::create(['package_id' => $package->id]);

        $m1 = $this->makeMember($cc, 'C-1', true);
        $m2 = $this->makeMember($cc, 'C-2', false);
        $m3 = $this->makeMember($cc, 'C-3', false);

        $source = $this->makeQuotation($cc, $m1, [$m1, $m2]);
        $target = $this->makeQuotation($cc, $m3, [$m3]);

        $totalReceipts = $this->totalReceiptAmount();

        // Move only member 1 out of the source (member 2 stays behind).
        $this->service()->combineQuotations((int) $cc->id, (int) $target->id, [(int) $m1->id]);

        // Source quotation survives with member 2 still linked.
        $this->assertNotNull(Quotation::find($source->id), 'Source quotation with remaining members should survive.');
        $sourceMemberIds = QuotationItem::where('quotation_id', $source->id)
            ->where('is_header', false)
            ->pluck('customer_confirmation_member_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertSame([(int) $m2->id], $sourceMemberIds);

        // Member 1 moved into the target quotation.
        $m1Item = QuotationItem::where('customer_confirmation_member_id', $m1->id)
            ->where('is_header', false)
            ->first();
        $this->assertSame((int) $target->id, (int) $m1Item->quotation_id);

        $this->assertSame($totalReceipts, $this->totalReceiptAmount());
    }

    public function test_combine_confirmations_rehomes_member_and_preserves_manifest(): void
    {
        PaymentStatusService::$suppressManifestAutoLink = true;
        $this->actingAs(User::factory()->create());

        $package = $this->makePackage();
        $target = CustomerConfirmation::create(['package_id' => $package->id]);
        $source = CustomerConfirmation::create(['package_id' => $package->id]);

        $m1 = $this->makeMember($target, 'T-1', true);
        $m2 = $this->makeMember($source, 'S-1', true);

        $this->makeQuotation($target, $m1, [$m1]);
        $sourceQuotation = $this->makeQuotation($source, $m2, [$m2]);

        // Member 2 is already assigned to the package manifest.
        $manifest = Manifest::create(['package_id' => $package->id]);
        $manifestMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $m2->id,
            'name' => 'Source Member',
        ]);

        $totalReceipts = $this->totalReceiptAmount();
        $totalInvoices = $this->totalInvoiceAmount();

        $this->service()->combineConfirmations((int) $source->id, (int) $target->id, [(int) $m2->id]);

        // Member + their quotation re-homed into the target confirmation.
        $this->assertSame((int) $target->id, (int) $m2->fresh()->customer_confirmation_id);
        $this->assertSame((int) $target->id, (int) $sourceQuotation->fresh()->customer_confirmation_id);

        // Emptied source confirmation removed.
        $this->assertNull(CustomerConfirmation::find($source->id));

        // Manifest assignment untouched — same row, same linkage.
        $manifestMember->refresh();
        $this->assertSame((int) $m2->id, (int) $manifestMember->customer_confirmation_member_id);
        $this->assertSame((int) $manifest->id, (int) $manifestMember->manifest_id);
        $this->assertSame(1, ManifestMember::where('manifest_id', $manifest->id)->count());

        // Payments intact.
        $this->assertSame($totalReceipts, $this->totalReceiptAmount());
        $this->assertSame($totalInvoices, $this->totalInvoiceAmount());
    }

    public function test_combine_confirmations_with_merge_option_produces_single_quotation(): void
    {
        PaymentStatusService::$suppressManifestAutoLink = true;
        $this->actingAs(User::factory()->create());

        $package = $this->makePackage();
        $target = CustomerConfirmation::create(['package_id' => $package->id]);
        $source = CustomerConfirmation::create(['package_id' => $package->id]);

        $m1 = $this->makeMember($target, 'T-1', true);
        $m2 = $this->makeMember($source, 'S-1', true);

        $targetQuotation = $this->makeQuotation($target, $m1, [$m1]);
        $sourceQuotation = $this->makeQuotation($source, $m2, [$m2]);

        $totalReceipts = $this->totalReceiptAmount();

        $this->service()->combineConfirmations(
            (int) $source->id,
            (int) $target->id,
            [(int) $m2->id],
            (int) $targetQuotation->id,
        );

        // Both members end up under the single target quotation.
        $this->assertNull(Quotation::find($sourceQuotation->id));
        $memberIds = QuotationItem::where('quotation_id', $targetQuotation->id)
            ->where('is_header', false)
            ->whereNotNull('customer_confirmation_member_id')
            ->pluck('customer_confirmation_member_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $this->assertSame([(int) $m1->id, (int) $m2->id], $memberIds);

        $this->assertNull(CustomerConfirmation::find($source->id));
        $this->assertSame($totalReceipts, $this->totalReceiptAmount());
    }

    public function test_combine_confirmations_blocks_when_quotation_shared_with_stay_behind_member(): void
    {
        PaymentStatusService::$suppressManifestAutoLink = true;
        $this->actingAs(User::factory()->create());

        $package = $this->makePackage();
        $target = CustomerConfirmation::create(['package_id' => $package->id]);
        $source = CustomerConfirmation::create(['package_id' => $package->id]);

        $m1 = $this->makeMember($target, 'T-1', true);
        $m2 = $this->makeMember($source, 'S-1', true);
        $m3 = $this->makeMember($source, 'S-2', false);

        $this->makeQuotation($target, $m1, [$m1]);
        // Source quotation covers BOTH source members.
        $this->makeQuotation($source, $m2, [$m2, $m3]);

        $this->expectException(HttpException::class);

        // Moving only member 2 would strand member 3's shared quotation.
        $this->service()->combineConfirmations((int) $source->id, (int) $target->id, [(int) $m2->id]);
    }

    public function test_combine_feature_flag_blocks_operations(): void
    {
        config(['customer_confirmation.combine_feature_enabled' => false]);

        $this->expectException(HttpException::class);

        $this->service()->combineQuotations(1, 1, [1]);
    }
}
