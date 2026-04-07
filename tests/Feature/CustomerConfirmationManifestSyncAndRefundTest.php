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
use App\Services\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerConfirmationManifestSyncAndRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_without_package_reports_paid_amount_as_overpaid_amount(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'No Package Overpaid Member',
            'email' => 'no-package-overpaid@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-NOPKG-001',
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => null,
            'date_of_application' => now()->format('Y-m-d'),
            'is_holding' => true,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'partially_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'No package paid item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 500,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'No package invoice',
            'amount' => 500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);

        $invoice->quotationItems()->sync([$item->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $holdingRows = app(CustomerConfirmationService::class)->getForHoldingIndex();
        $groupRow = collect($holdingRows)->firstWhere('id', $group->id);

        $this->assertNotNull($groupRow);
        $this->assertSame(500.0, (float) ($groupRow['paid_amount'] ?? 0));
        $this->assertSame(0.0, (float) ($groupRow['total_amount'] ?? 0));
        $this->assertSame(500.0, (float) ($groupRow['overpaid_amount'] ?? 0));

        $memberRow = collect($groupRow['members'] ?? [])->firstWhere('id', $member->id);
        $this->assertNotNull($memberRow);
        $this->assertSame(500.0, (float) ($memberRow['paid_amount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRow['total_amount'] ?? 0));
        $this->assertSame(500.0, (float) ($memberRow['overpaid_amount'] ?? 0));
    }

    public function test_confirmed_index_total_amount_uses_package_sharing_plan_price_without_discount_extension_adjustment(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Package Price Member',
            'email' => 'package-price-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-PKG-PRICE-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-PRICE-001',
            'name' => 'Package Price Rule',
            'status' => 'open',
            'price_single' => 1000,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
            'is_holding' => false,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'partially_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
            'extensions' => [
                [
                    'name' => 'Manual Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => -300,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package-only item',
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
            'description' => 'Discounted invoice',
            'amount' => 700,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);

        $invoice->quotationItems()->sync([$item->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 700,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $confirmedRows = app(CustomerConfirmationService::class)->getForConfirmedIndex();
        $groupRow = collect($confirmedRows)->firstWhere('id', $group->id);

        $this->assertNotNull($groupRow);
        $this->assertSame(700.0, (float) ($groupRow['paid_amount'] ?? 0));
        $this->assertSame(1000.0, (float) ($groupRow['total_amount'] ?? 0));

        $memberRow = collect($groupRow['members'] ?? [])->firstWhere('id', $member->id);
        $this->assertNotNull($memberRow);
        $this->assertSame(700.0, (float) ($memberRow['paid_amount'] ?? 0));
        $this->assertSame(1000.0, (float) ($memberRow['total_amount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRow['overpaid_amount'] ?? 0));
    }

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
            'passport_path' => 'customers/passport/passport-sync-member.pdf',
            'photo_path' => 'customers/photo/photo-sync-member.jpg',
        ]);

        $customer->files()->create([
            'field' => 'passport',
            'file_name' => 'Member Passport.pdf',
            'file_path' => 'customers/passport/passport-sync-member.pdf',
        ]);

        $customer->files()->create([
            'field' => 'photo',
            'file_name' => 'Member Photo.jpg',
            'file_path' => 'customers/photo/photo-sync-member.jpg',
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

        $openPassportFile = $openManifest->files()
            ->where('field', 'passport')
            ->first();
        $openPhotoFile = $openManifest->files()
            ->where('field', 'photo')
            ->first();

        $this->assertNotNull($openPassportFile);
        $this->assertNotNull($openPhotoFile);
        $this->assertSame(
            'customers/passport/passport-sync-member.pdf',
            (string) $openPassportFile?->file_path,
        );
        $this->assertSame(
            'customers/photo/photo-sync-member.jpg',
            (string) $openPhotoFile?->file_path,
        );

        $this->assertSame('Original Name', (string) $closedManifestMember->name);
        $this->assertSame('OLD-PASSPORT', (string) $closedManifestMember->passport_number);
        $this->assertSame('single', (string) $closedManifestMember->sharing_plan);
        $this->assertSame('Self', (string) $closedManifestMember->relationship);

        $this->assertFalse(
            $closedManifest->files()->whereIn('field', ['passport', 'photo'])->exists(),
        );
    }

    public function test_customer_confirmation_member_sync_uses_customer_file_paths_when_customer_columns_are_empty(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'File Path Fallback Member',
            'email' => 'file-path-fallback@example.com',
            'contact' => '18887777',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-FILE-FALLBACK-001',
            'passport_path' => null,
            'photo_path' => null,
        ]);

        $customer->files()->create([
            'field' => 'passport',
            'file_name' => 'Fallback Passport.pdf',
            'file_path' => 'customers/passport/fallback-passport.pdf',
        ]);

        $customer->files()->create([
            'field' => 'photo',
            'file_name' => 'Fallback Photo.jpg',
            'file_path' => 'customers/photo/fallback-photo.jpg',
        ]);

        $group = CustomerConfirmation::create([
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
            'package_number' => 'PKG-FILE-FALLBACK-OPEN',
            'name' => 'File Path Fallback Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $openPackage->id,
            'manifest_number' => 'MNF-FILE-FALLBACK-001',
        ]);

        $manifestMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'name' => 'File Path Fallback Member',
            'passport_path' => null,
            'photo_path' => null,
            'sharing_plan' => 'single',
            'relationship' => 'Self',
        ]);

        app(CustomerConfirmationService::class)->updateMemberDetails(
            (int) $member->id,
            [
                'status' => 'pending_payment',
                'sharing_plan' => 'single',
                'relationship' => 'Self',
            ],
        );

        $manifestMember->refresh();

        $this->assertSame(
            'customers/passport/fallback-passport.pdf',
            (string) $manifestMember->passport_path,
        );
        $this->assertSame(
            'customers/photo/fallback-photo.jpg',
            (string) $manifestMember->photo_path,
        );

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'passport',
            'file_path' => 'customers/passport/fallback-passport.pdf',
        ]);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'photo',
            'file_path' => 'customers/photo/fallback-photo.jpg',
        ]);
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
            'refund_type' => 'cancel',
            'member_refunds' => [
                [
                    'member_id' => $member->id,
                    'mode' => 'percentage',
                    'percentage' => 50,
                ],
            ],
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundInvoice = Invoice::query()
            ->where('order_id', $order->id)
            ->where('status', 'refund')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundInvoice);
        $this->assertNotSame((int) $invoice->id, (int) $refundInvoice->id);
        $this->assertSame(-500.0, (float) ($refundInvoice->amount ?? 0));

        $refundItems = $refundInvoice->quotationItems()
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $refundItems);

        $refundHeader = $refundItems->firstWhere('is_header', true);
        $refundDetail = $refundItems->firstWhere('is_header', false);

        $this->assertNotNull($refundHeader);
        $this->assertNotNull($refundDetail);
        $this->assertSame('Refund', (string) ($refundHeader?->description ?? ''));
        $this->assertSame('Refund - Refund Member', (string) ($refundDetail?->description ?? ''));
        $this->assertSame((int) ($refundHeader?->id ?? 0), (int) ($refundDetail?->parent_id ?? 0));
        $this->assertSame(-500.0, (float) ($refundDetail?->rate ?? 0));

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $refundInvoice->id)
            ->where('amount', '-500.00')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundReceipt);
        $this->assertSame('transfer', (string) ($refundReceipt->payment_method ?? ''));
        $this->assertSame('Receipt For Refund', (string) ($refundReceipt->description ?? ''));

        $member->refresh();

        $this->assertSame('cancelled', $member->status);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex();
        $groupRow = collect($grouped)->firstWhere('id', $group->id);

        $this->assertNotNull($groupRow);
        $this->assertSame(0.0, (float) ($groupRow['paid_amount'] ?? 0));
    }

    public function test_customer_confirmation_member_refund_allows_zero_amount_and_creates_refund_receipt(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Refund Zero Member',
            'email' => 'refund-zero-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-REFUND-002',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-REFUND-002',
            'name' => 'Refund Zero Package',
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
            'status' => 'partially_paid',
            'sharing_plan' => 'single',
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

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->post(route('customer-confirmations.refunds.store', $group->id), [
            'refund_type' => 'cancel',
            'member_refunds' => [
                [
                    'member_id' => $member->id,
                    'mode' => 'fixed',
                    'amount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundInvoice = Invoice::query()
            ->where('order_id', $order->id)
            ->where('status', 'refund')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundInvoice);

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $refundInvoice->id)
            ->where('payment_method', 'transfer')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundReceipt);
        $this->assertSame(0.0, (float) ($refundReceipt->amount ?? 0));
        $this->assertSame('Receipt For Refund', (string) ($refundReceipt->description ?? ''));
    }

    public function test_customer_confirmation_member_refund_uses_custom_payment_method_and_description(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Refund Custom Member',
            'email' => 'refund-custom-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-REFUND-003',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-REFUND-003',
            'name' => 'Refund Custom Package',
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
            'status' => 'partially_paid',
            'sharing_plan' => 'single',
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
            'description' => 'Custom Base Item',
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
            'description' => 'Custom Base Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
            'payment_method' => 'transfer',
        ]);

        $invoice->quotationItems()->sync([$baseItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->post(route('customer-confirmations.refunds.store', $group->id), [
            'refund_type' => 'cancel',
            'member_refunds' => [
                [
                    'member_id' => $member->id,
                    'mode' => 'fixed',
                    'amount' => 100,
                    'payment_method' => 'cash',
                    'description' => 'Receipt For Refund',
                ],
            ],
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundInvoice = Invoice::query()
            ->where('order_id', $order->id)
            ->where('status', 'refund')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundInvoice);

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $refundInvoice->id)
            ->where('amount', '-100.00')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundReceipt);
        $this->assertSame('cash', (string) ($refundReceipt->payment_method ?? ''));
        $this->assertSame('Receipt For Refund', (string) ($refundReceipt->description ?? ''));
    }

    public function test_customer_confirmation_overpaid_refund_keeps_member_status_active(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Overpaid Member',
            'email' => 'overpaid-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-OVERPAID-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-OVERPAID-001',
            'name' => 'Overpaid Package',
            'status' => 'open',
            'price_single' => 4000,
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
            'description' => 'Overpaid Base Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Overpaid Base Invoice',
            'amount' => 5000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
            'payment_method' => 'transfer',
        ]);

        $invoice->quotationItems()->sync([$baseItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->post(route('customer-confirmations.refunds.store', $group->id), [
            'refund_type' => 'overpaid',
            'member_refunds' => [
                [
                    'member_id' => $member->id,
                    'mode' => 'fixed',
                    'amount' => 1000,
                ],
            ],
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundInvoice = Invoice::query()
            ->where('order_id', $order->id)
            ->where('status', 'refund')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundInvoice);
        $this->assertSame(-1000.0, (float) ($refundInvoice->amount ?? 0));

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $refundInvoice->id)
            ->where('amount', '-1000.00')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundReceipt);

        $member->refresh();
        $this->assertNotSame('cancelled', $member->status);
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
            'description' => 'Quotation Package - Quoted Member - Single sharing',
        ]);
    }

    public function test_manifest_member_add_auto_syncs_passport_and_photo_documents(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Manifest Auto Doc Member',
            'email' => 'manifest-auto-doc@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-MANIFEST-AUTO-001',
            'passport_path' => 'customers/passport/manifest-auto-passport.pdf',
            'photo_path' => 'customers/photo/manifest-auto-photo.jpg',
        ]);

        $group = CustomerConfirmation::create([
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

        $package = Package::create([
            'package_number' => 'PKG-MANIFEST-AUTO-001',
            'name' => 'Manifest Auto Doc Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-MANIFEST-AUTO-001',
        ]);

        app(ManifestService::class)->update([
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'customer_confirmation_id' => $group->id,
                    'sharing_plan' => 'single',
                    'name_as_per_passport' => 'Manifest Auto Doc Member',
                    'relationship' => 'Self',
                ],
            ],
        ], $manifest->id);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'passport',
            'file_path' => 'customers/passport/manifest-auto-passport.pdf',
        ]);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'photo',
            'file_path' => 'customers/photo/manifest-auto-photo.jpg',
        ]);
    }

    public function test_manifest_edit_payload_includes_stored_customer_document_file_names(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Manifest Name Sync Member',
            'email' => 'manifest-name-sync@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-MANIFEST-NAME-001',
            'passport_path' => 'customers/passport/name-sync-passport.pdf',
            'photo_path' => 'customers/photo/name-sync-photo.jpg',
        ]);

        $customer->files()->create([
            'field' => 'passport',
            'file_name' => 'Passport Manifest Name Sync',
            'file_path' => 'customers/passport/name-sync-passport.pdf',
        ]);

        $customer->files()->create([
            'field' => 'photo',
            'file_name' => 'Photo Manifest Name Sync',
            'file_path' => 'customers/photo/name-sync-photo.jpg',
        ]);

        $group = CustomerConfirmation::create([
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

        $package = Package::create([
            'package_number' => 'PKG-MANIFEST-NAME-001',
            'name' => 'Manifest Name Sync Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-MANIFEST-NAME-001',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Manifest Name Sync Member',
            'sharing_plan' => 'single',
            'relationship' => 'Self',
            'passport_path' => 'customers/passport/name-sync-passport.pdf',
            'photo_path' => 'customers/photo/name-sync-photo.jpg',
        ]);

        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $memberRow = collect($payload['members'] ?? [])->first();

        $this->assertNotNull($memberRow);
        $this->assertSame('Passport Manifest Name Sync', (string) ($memberRow['passport_file_name'] ?? ''));
        $this->assertSame('Photo Manifest Name Sync', (string) ($memberRow['photo_file_name'] ?? ''));
    }

    public function test_customer_confirmation_update_preserves_paid_member_billing_when_adding_member(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $primaryUser = User::factory()->create([
            'name' => 'Paid Member',
            'email' => 'paid-member-preserve@example.com',
            'contact' => '81112222',
        ]);

        $primaryCustomer = Customer::create([
            'user_id' => $primaryUser->id,
            'customer_number' => 'CUST-PRESERVE-001',
            'passport_number' => 'PAID-PASS-001',
        ]);

        $newMemberUser = User::factory()->create([
            'name' => 'New Added Member',
            'email' => 'new-added-member@example.com',
            'contact' => '83334444',
        ]);

        $newMemberCustomer = Customer::create([
            'user_id' => $newMemberUser->id,
            'customer_number' => 'CUST-PRESERVE-NEW-001',
            'passport_number' => 'NEW-PASS-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-PRESERVE-001',
            'name' => 'Preserve Billing Package',
            'status' => 'open',
            'price_single' => 1000,
            'price_double' => 900,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $paidMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $primaryCustomer->id,
            'is_leader' => true,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
            'relationship' => 'Self',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $primaryCustomer->id,
            'customer_confirmation_id' => $group->id,
            'created_by' => $authUser->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $paidMember->id,
            'description' => 'Paid member package item',
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
            'description' => 'Paid member invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);

        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->put(route('customer-confirmations.update', $group->id), [
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
            'members' => [
                [
                    'member_id' => $paidMember->id,
                    'customer_id' => $primaryCustomer->id,
                    'is_leader' => true,
                    'name' => 'Paid Member',
                    'email' => 'paid-member-preserve@example.com',
                    'contact_number' => '81112222',
                    'status' => 'fully_paid',
                    'sharing_plan' => 'double',
                    'relationship' => 'Self',
                ],
                [
                    'customer_id' => $newMemberCustomer->id,
                    'is_leader' => false,
                    'name' => 'New Added Member',
                    'email' => 'new-added-member@example.com',
                    'contact_number' => '83334444',
                    'status' => 'pending_payment',
                    'sharing_plan' => 'single',
                    'relationship' => 'Sibling',
                ],
            ],
        ]);

        $response->assertRedirect();

        $paidMember->refresh();
        $quotationItem->refresh();

        $this->assertSame($paidMember->id, $quotationItem->customer_confirmation_member_id);
        $this->assertSame('double', (string) $paidMember->sharing_plan);
        $this->assertSame('overpaid', (string) $paidMember->status);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex();
        $groupRow = collect($grouped)->firstWhere('id', $group->id);

        $this->assertNotNull($groupRow);
        $memberRows = collect($groupRow['members'] ?? []);
        $paidMemberRow = $memberRows->firstWhere('id', $paidMember->id);

        $this->assertNotNull($paidMemberRow);
        $this->assertSame(1000.0, (float) ($paidMemberRow['paid_amount'] ?? 0));
        $this->assertTrue((bool) ($paidMemberRow['has_quotation'] ?? false));
    }

    public function test_sharing_plan_upgrade_creates_balance_invoice_automatically(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Balance Invoice Member',
            'email' => 'balance-invoice-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-BALANCE-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-BALANCE-001',
            'name' => 'Balance Invoice Package',
            'status' => 'open',
            'price_single' => 1000,
            'price_double' => 1400,
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

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $packageItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Initial Package Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $initialInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Initial Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
            'payment_method' => 'transfer',
        ]);

        $initialInvoice->quotationItems()->sync([$packageItem->id]);

        Receipt::create([
            'invoice_id' => $initialInvoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        app(CustomerConfirmationService::class)->updateMemberDetails((int) $member->id, [
            'status' => 'fully_paid',
            'sharing_plan' => 'double',
            'relationship' => 'Self',
        ]);

        $member->refresh();
        $this->assertSame('partially_paid', (string) $member->status);

        $balanceInvoice = Invoice::query()
            ->where('order_id', $order->id)
            ->where('description', 'Invoice For Balance')
            ->latest('id')
            ->first();

        $this->assertNotNull($balanceInvoice);
        $this->assertSame('issued', (string) ($balanceInvoice->status ?? ''));
        $this->assertSame(400.0, (float) ($balanceInvoice->amount ?? 0));

        $balanceItems = $balanceInvoice->quotationItems()
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $balanceItems);

        $balanceHeader = $balanceItems->firstWhere('is_header', true);
        $balanceDetail = $balanceItems->firstWhere('is_header', false);

        $this->assertNotNull($balanceHeader);
        $this->assertNotNull($balanceDetail);
        $this->assertSame('Umrah Packages', (string) ($balanceHeader?->description ?? ''));
        $this->assertSame((int) $member->id, (int) ($balanceDetail?->customer_confirmation_member_id ?? 0));
        $this->assertSame((int) ($balanceHeader?->id ?? 0), (int) ($balanceDetail?->parent_id ?? 0));
        $this->assertSame(400.0, (float) ($balanceDetail?->rate ?? 0));
        $this->assertDatabaseMissing('receipts', [
            'invoice_id' => $balanceInvoice->id,
        ]);
    }

    public function test_sharing_plan_change_with_auto_sync_disabled_updates_non_converted_umrah_item_without_creating_invoice(): void
    {
        config(['customer_confirmation.auto_sync_billing_mutations' => false]);

        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Draft Member',
            'email' => 'draft-member-sync@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-DRAFT-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-DRAFT-001',
            'name' => 'Draft Sync Package',
            'status' => 'open',
            'price_single' => 1000,
            'price_double' => 1400,
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

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'draft',
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => null,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $memberItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'parent_id' => $header->id,
            'description' => 'Draft Sync Package - Draft Member - Single sharing',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 2,
        ]);

        app(CustomerConfirmationService::class)->updateMemberDetails((int) $member->id, [
            'status' => 'pending_payment',
            'sharing_plan' => 'double',
            'relationship' => 'Self',
        ]);

        $memberItem->refresh();

        $this->assertSame(1400.0, (float) ($memberItem->rate ?? 0));
        $this->assertSame(
            'Draft Sync Package - Draft Member - Double sharing',
            (string) ($memberItem->description ?? ''),
        );

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_manual_sync_billing_applies_reconciliation_when_auto_sync_is_disabled(): void
    {
        config(['customer_confirmation.auto_sync_billing_mutations' => false]);

        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Manual Sync Member',
            'email' => 'manual-sync-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-MANUAL-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-MANUAL-001',
            'name' => 'Manual Sync Package',
            'status' => 'open',
            'price_single' => 1000,
            'price_double' => 1400,
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

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $packageItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Initial Package Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $initialInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Initial Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
            'payment_method' => 'transfer',
        ]);

        $initialInvoice->quotationItems()->sync([$packageItem->id]);

        Receipt::create([
            'invoice_id' => $initialInvoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        app(CustomerConfirmationService::class)->updateMemberDetails((int) $member->id, [
            'status' => 'fully_paid',
            'sharing_plan' => 'double',
            'relationship' => 'Self',
        ]);

        $this->assertDatabaseMissing('invoices', [
            'order_id' => $order->id,
            'description' => 'Invoice For Balance',
        ]);

        $response = $this->post(route('customer-confirmations.sync-billing', [
            'id' => $group->id,
        ]));

        $response->assertRedirect();

        $balanceInvoice = Invoice::query()
            ->where('order_id', $order->id)
            ->where('description', 'Invoice For Balance')
            ->latest('id')
            ->first();

        $this->assertNotNull($balanceInvoice);
        $this->assertSame(400.0, (float) ($balanceInvoice->amount ?? 0));
    }

    public function test_sharing_plan_downgrade_adjusts_existing_outstanding_invoice_without_void_invoice(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Void Unpaid Member',
            'email' => 'void-unpaid-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-VOID-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-VOID-001',
            'name' => 'Void Adjustment Package',
            'status' => 'open',
            'price_single' => 7000,
            'price_double' => 4000,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'partially_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $umrahHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => null,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'quantity' => 1,
            'rate' => null,
            'sort_order' => 1,
        ]);

        $packageItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'parent_id' => $umrahHeader->id,
            'description' => 'Original package item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 7000,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Original package invoice',
            'amount' => 7000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
            'payment_method' => 'transfer',
        ]);

        $invoice->quotationItems()->sync([$umrahHeader->id, $packageItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        app(CustomerConfirmationService::class)->updateMemberDetails((int) $member->id, [
            'status' => 'partially_paid',
            'sharing_plan' => 'double',
            'relationship' => 'Self',
        ]);

        $this->assertDatabaseMissing('invoices', [
            'order_id' => $order->id,
            'description' => 'Voided Unpaid Previous Package Billing',
        ]);

        $invoice->refresh();
        $this->assertSame(5000.0, (float) ($invoice->amount ?? 0));

        $adjustmentItems = $invoice->quotationItems()
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $adjustmentItems);

        $adjustmentHeader = $adjustmentItems->first(function ($item) {
            return (bool) $item->is_header && (string) ($item->description ?? '') === 'Umrah Packages';
        });

        $adjustmentDetail = $adjustmentItems->first(function ($item) use ($member) {
            return ! (bool) $item->is_header
                && (int) ($item->customer_confirmation_member_id ?? 0) === (int) $member->id
                && (float) ($item->rate ?? 0) === -2000.0;
        });

        $this->assertNotNull($adjustmentHeader);
        $this->assertNotNull($adjustmentDetail);
        $this->assertSame(
            1,
            QuotationItem::query()
                ->where('quotation_id', $quotation->id)
                ->where('is_header', true)
                ->whereRaw('LOWER(TRIM(description)) = ?', ['umrah packages'])
                ->count(),
        );
        $this->assertSame((int) ($adjustmentHeader?->id ?? 0), (int) ($adjustmentDetail?->parent_id ?? 0));
        $this->assertSame((int) $member->id, (int) ($adjustmentDetail?->customer_confirmation_member_id ?? 0));
        $this->assertSame(-2000.0, (float) ($adjustmentDetail?->rate ?? 0));

        $member->refresh();
        $this->assertSame('overpaid', (string) ($member->status ?? ''));
    }

    public function test_cancel_member_unpaid_removes_only_target_member_item_from_shared_active_quotation(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $firstCustomerUser = User::factory()->create([
            'name' => 'Cancel Shared Member A',
            'email' => 'cancel-shared-member-a@example.com',
        ]);

        $secondCustomerUser = User::factory()->create([
            'name' => 'Cancel Shared Member B',
            'email' => 'cancel-shared-member-b@example.com',
        ]);

        $firstCustomer = Customer::create([
            'user_id' => $firstCustomerUser->id,
            'customer_number' => 'CUST-CANCEL-SHARED-001',
        ]);

        $secondCustomer = Customer::create([
            'user_id' => $secondCustomerUser->id,
            'customer_number' => 'CUST-CANCEL-SHARED-002',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-CANCEL-SHARED-001',
            'name' => 'Cancel Shared Package',
            'status' => 'open',
            'price_single' => 2000,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $firstMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $firstCustomer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $secondMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $secondCustomer->id,
            'is_leader' => false,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $firstCustomer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'draft',
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $firstMember->id,
            'parent_id' => $header->id,
            'description' => 'Shared Member A Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 2000,
            'sort_order' => 2,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $secondMember->id,
            'parent_id' => $header->id,
            'description' => 'Shared Member B Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 2000,
            'sort_order' => 3,
        ]);

        $response = $this->from(route('confirmed-customer.index'))
            ->post(route('customer-confirmations.members.cancel', [
                'memberId' => $firstMember->id,
            ]));

        $response->assertRedirect(route('confirmed-customer.index'));

        $firstMember->refresh();
        $quotation->refresh();

        $this->assertSame('cancelled', (string) ($firstMember->status ?? ''));
        $this->assertSame('draft', (string) ($quotation->status?->value ?? $quotation->status ?? ''));

        $this->assertDatabaseMissing('quotation_items', [
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $firstMember->id,
            'description' => 'Shared Member A Item',
        ]);

        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $secondMember->id,
            'description' => 'Shared Member B Item',
        ]);
    }

    public function test_cancel_member_unpaid_cancels_quotation_when_member_is_last_billable_item(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Cancel Solo Member',
            'email' => 'cancel-solo-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-CANCEL-SOLO-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-CANCEL-SOLO-001',
            'name' => 'Cancel Solo Package',
            'status' => 'open',
            'price_single' => 1500,
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

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'draft',
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'parent_id' => $header->id,
            'description' => 'Solo Member Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1500,
            'sort_order' => 2,
        ]);

        $response = $this->from(route('confirmed-customer.index'))
            ->post(route('customer-confirmations.members.cancel', [
                'memberId' => $member->id,
            ]));

        $response->assertRedirect(route('confirmed-customer.index'));

        $quotationId = (int) $quotation->id;

        $member->refresh();

        $this->assertSame('cancelled', (string) ($member->status ?? ''));
        $this->assertDatabaseMissing('quotations', [
            'id' => $quotationId,
        ]);
        $this->assertSame(0, QuotationItem::query()->where('quotation_id', $quotationId)->count());
    }

    public function test_cancel_member_with_paid_amount_is_rejected_and_must_use_refund_flow(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Cancel Paid Member',
            'email' => 'cancel-paid-member@example.com',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-CANCEL-PAID-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-CANCEL-PAID-001',
            'name' => 'Cancel Paid Package',
            'status' => 'open',
            'price_single' => 1800,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'partially_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Paid Member Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1800,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Paid Member Invoice',
            'amount' => 1800,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
            'payment_method' => 'transfer',
        ]);

        $invoice->quotationItems()->sync([$item->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1800,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->from(route('confirmed-customer.index'))
            ->post(route('customer-confirmations.members.cancel', [
                'memberId' => $member->id,
            ]));

        $response->assertRedirect(route('confirmed-customer.index'));
        $response->assertSessionHasErrors('member');

        $member->refresh();
        $this->assertNotSame('cancelled', (string) ($member->status ?? ''));
    }
}
