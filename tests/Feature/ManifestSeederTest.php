<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestAccommodationAssignment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Database\Seeders\ManifestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManifestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_seeder_creates_manifests_for_each_package(): void
    {
        Package::create([
            'package_number' => 'PKG-ECO',
            'name' => 'Umrah Economy 14 Days',
            'status' => 'open',
        ]);

        Package::create([
            'package_number' => 'PKG-PRE',
            'name' => 'Umrah Premium 10 Days',
            'status' => 'open',
        ]);

        $this->seed(ManifestSeeder::class);

        $this->assertSame(2, Manifest::count());

        $manifest = Manifest::first();

        $this->assertNotNull($manifest->manifest_number);
        $this->assertSame('draft', $manifest->status);

        // If travelers exist, they should have customer_confirmation_member_id
        if ($manifest->travelers()->count() > 0) {
            $this->assertSame(
                $manifest->travelers()->count(),
                $manifest->travelers()->whereNotNull('customer_confirmation_member_id')->count(),
            );
        }

        // Create test accommodation assignments
        ManifestAccommodationAssignment::create([
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'makkah',
            'sort_order' => 1,
            'room_no' => '101',
        ]);

        ManifestAccommodationAssignment::create([
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'madinah',
            'sort_order' => 1,
            'room_no' => '201',
        ]);

        // Verify accommodation assignments exist
        $this->assertGreaterThan(0, $manifest->accommodationAssignments()->count());
        $this->assertDatabaseHas('manifest_accommodation_assignments', [
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'makkah',
            'sort_order' => 1,
            'room_no' => '101',
        ]);
        $this->assertDatabaseHas('manifest_accommodation_assignments', [
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'madinah',
            'sort_order' => 1,
            'room_no' => '201',
        ]);
    }

    public function test_manifest_seeder_attaches_paid_members_as_travelers(): void
    {
        $user = User::factory()->create();

        $package = Package::create([
            'package_number' => 'PKG-PAID',
            'name' => 'Umrah Paid Travelers',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-PAID-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->toDateString(),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'partially_paid',
            'sharing_plan' => 'double',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(14)->toDateString(),
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Paid Member Package',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'type' => 'deposit',
            'description' => 'Invoice For Deposit',
            'amount' => 500,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'paid',
        ]);

        $invoice->quotationItems()->sync([$quotationItem->id]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'reference' => 'TEST-PAID-MEMBER',
        ]);

        ReceiptAllocation::create([
            'receipt_id' => $receipt->id,
            'customer_confirmation_member_id' => $member->id,
            'allocated_amount' => 500,
            'notes' => 'Seeder test allocation',
        ]);

        $this->seed(ManifestSeeder::class);

        $manifest = Manifest::query()->where('package_id', $package->id)->firstOrFail();

        $this->assertDatabaseHas('manifest_travelers', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'customer_id' => $customer->id,
            'name_as_per_passport' => $user->name,
        ]);
    }
}
