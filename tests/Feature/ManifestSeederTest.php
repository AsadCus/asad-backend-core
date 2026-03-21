<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestRoomMember;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Database\Seeders\ManifestSeeder;
use Database\Seeders\PackageSeeder;
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
        $this->assertSame('open', $manifest->package?->status);

        // If members exist, they should have customer_confirmation_member_id
        if ($manifest->members()->count() > 0) {
            $this->assertSame(
                $manifest->members()->count(),
                $manifest->members()->whereNotNull('customer_confirmation_member_id')->count(),
            );
        }

        $this->assertNotNull($manifest->id);
    }

    public function test_manifest_seeder_attaches_paid_members_as_members(): void
    {
        $user = User::factory()->create();

        $package = Package::create([
            'package_number' => 'PKG-PAID',
            'name' => 'Umrah Paid Members',
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

        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);
    }

    public function test_manifest_seeder_groups_rooms_by_confirmation_and_sharing_plan_with_capacity_split(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-RM-001',
            'name' => 'Room Grouping Package',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->toDateString(),
        ]);

        $members = collect();

        foreach (['double', 'double', 'double', 'double', 'single'] as $index => $sharingPlan) {
            $user = User::factory()->create();
            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_number' => 'CUST-RM-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ]);

            $member = CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $index === 0,
                'status' => 'partially_paid',
                'sharing_plan' => $sharingPlan,
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
                'description' => 'Seeder room split test',
                'is_header' => false,
                'quantity' => 1,
                'rate' => 4000,
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
                'reference' => 'TEST-RM-SPLIT-'.($index + 1),
            ]);

            ReceiptAllocation::create([
                'receipt_id' => $receipt->id,
                'customer_confirmation_member_id' => $member->id,
                'allocated_amount' => 500,
                'notes' => 'Seeder room split allocation',
            ]);

            $members->push($member);
        }

        $this->seed(ManifestSeeder::class);

        $manifest = Manifest::query()->where('package_id', $package->id)->firstOrFail();

        $this->assertSame(3, $manifest->rooms()->count());
        $this->assertSame(5, $manifest->rooms()->withCount('roomMembers')->get()->sum('room_members_count'));

        $doubleRooms = $manifest->rooms()->where('sharing_plan', 'double')->orderBy('id')->get();
        $singleRooms = $manifest->rooms()->where('sharing_plan', 'single')->orderBy('id')->get();

        $this->assertCount(2, $doubleRooms);
        $this->assertCount(1, $singleRooms);
        $this->assertSame([2, 2], $doubleRooms->map(fn ($room) => $room->roomMembers()->count())->all());
        $this->assertSame(1, $singleRooms->first()->roomMembers()->count());
    }

    public function test_manifest_seeder_populates_official_sharing_groups_and_room_members(): void
    {
        $this->seed(PackageSeeder::class);
        $this->seed(ManifestSeeder::class);

        $manifest = Manifest::query()
            ->whereHas('package.officials')
            ->with(['package.officials', 'members', 'rooms.roomMembers'])
            ->firstOrFail();

        $officialMembers = $manifest->members->whereNotNull('package_official_id')->values();

        $this->assertTrue($officialMembers->isNotEmpty());

        $this->assertTrue($officialMembers->every(function ($member): bool {
            return $member->manifest_sharing_group_id !== null
                && $member->role !== null
                && $member->sharing_plan === 'single';
        }));

        $officialMemberIds = $officialMembers->pluck('id')->all();

        $officialRoomMembers = ManifestRoomMember::query()
            ->whereIn('manifest_member_id', $officialMemberIds)
            ->get();

        $this->assertTrue($officialRoomMembers->isNotEmpty());
    }
}
