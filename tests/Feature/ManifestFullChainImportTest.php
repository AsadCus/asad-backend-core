<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestSharingGroup;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ManifestImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManifestFullChainImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    private function makePackage(): Package
    {
        // status 'open' + seats are deliberate: they make
        // PackageSeatService::hasAvailableSeat() return true, which (without the
        // import's auto-link suppression) would let the Receipt boot hook
        // auto-create DUPLICATE manifest members. The tests assert this does NOT
        // happen, so the package must allow seat intake.
        return Package::create([
            'name' => 'Umrah Test Package',
            'status' => 'open',
            'total_seats' => 50,
            'seats_left' => 50,
            'price_single' => 4000,
            'price_double' => 5000,
            'price_triple' => 4500,
            'price_quad' => 4000,
            'child_with_bed_price' => 3000,
            'child_no_bed_price' => 2000,
            'infant_price' => 1000,
        ]);
    }

    private function makeManifest(Package $package): Manifest
    {
        return Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-TEST-'.$package->id,
        ]);
    }

    private function service(): ManifestImportService
    {
        return app(ManifestImportService::class);
    }

    private function memberRow(array $overrides = []): array
    {
        return array_merge([
            'member_key' => null,
            'booking_ref' => null,
            'payer_ref' => null,
            'sharing_group_key' => null,
            'relationship' => null,
            'name' => 'Member',
            'email' => null,
            'contact' => null,
            'nric_number' => null,
            'passport_number' => null,
            'passport_issue_date' => null,
            'passport_expiry_date' => null,
            'passport_place_of_issue' => null,
            'nationality' => null,
            'gender' => null,
            'date_of_birth' => null,
            'address' => null,
            'sharing_plan' => 'double',
            'is_leader' => false,
            'has_chronic_disease' => false,
            'is_using_wheelchair' => false,
        ], $overrides);
    }

    // ── Scenario 1: group quotation + installment invoices + receipts ──────────

    public function test_group_quotation_with_installments_builds_full_chain(): void
    {
        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        // Booking B1: Ahmad (payer) covers Ahmad + Siti, both double => total 10000.
        // Booking B2: Omar, single self-pay => 4000.
        $members = [
            $this->memberRow([
                'member_key' => 'M1', 'booking_ref' => 'B1', 'payer_ref' => '',
                'sharing_group_key' => 'R1', 'name' => 'Ahmad', 'passport_number' => 'A1',
                'sharing_plan' => 'double', 'is_leader' => true,
            ]),
            $this->memberRow([
                'member_key' => 'M2', 'booking_ref' => 'B1', 'payer_ref' => 'M1',
                'sharing_group_key' => 'R1', 'name' => 'Siti', 'passport_number' => 'A2',
                'sharing_plan' => 'double',
            ]),
            $this->memberRow([
                'member_key' => 'M3', 'booking_ref' => 'B2', 'payer_ref' => '',
                'sharing_group_key' => 'R2', 'name' => 'Omar', 'passport_number' => 'A3',
                'sharing_plan' => 'single', 'is_leader' => true,
            ]),
        ];

        $payments = [
            // B1 group total 10000 split into deposit (paid) + balance (unpaid).
            ['booking_ref' => 'B1', 'payer_ref' => 'M1', 'installment_no' => 1, 'invoice_amount' => 6000,
                'invoice_date' => '2024-01-10', 'due_date' => '2024-01-10', 'paid_amount' => 6000,
                'paid_date' => '2024-01-10', 'payment_method' => 'bank_transfer', 'reference' => 'TXN1'],
            ['booking_ref' => 'B1', 'payer_ref' => 'M1', 'installment_no' => 2, 'invoice_amount' => 4000,
                'invoice_date' => '2024-02-10', 'due_date' => '2024-02-10', 'paid_amount' => null,
                'paid_date' => null, 'payment_method' => null, 'reference' => null],
            // B2 single 4000 paid in full.
            ['booking_ref' => 'B2', 'payer_ref' => 'M3', 'installment_no' => 1, 'invoice_amount' => 4000,
                'invoice_date' => '2024-01-15', 'due_date' => '2024-01-15', 'paid_amount' => 4000,
                'paid_date' => '2024-01-15', 'payment_method' => 'cash', 'reference' => 'RCT9'],
        ];

        $result = $this->service()->importFromPayload($manifest, [], $members, $payments);

        $this->assertSame([], $result['errors'], json_encode($result['errors']));
        $this->assertSame(3, $result['imported_members']);
        $this->assertSame(2, $result['bookings']);

        // Two bookings => two confirmations, each with one enquiry.
        $this->assertSame(2, CustomerConfirmation::count());
        // One group quotation for B1 (covers 2) + one for B2 = 2 quotations, all converted.
        $this->assertSame(2, Quotation::count());
        $this->assertSame(2, Quotation::where('status', 'converted')->count());
        $this->assertSame(2, Order::count());
        // B1 has 2 installment invoices, B2 has 1 => 3 invoices, 2 receipts.
        $this->assertSame(3, Invoice::count());
        $this->assertSame(2, Receipt::count());
        $this->assertSame(3, ManifestMember::count());

        // B1's quotation is owned by Ahmad's customer and carries 2 line items.
        $ahmad = Customer::whereHas('user', fn ($q) => $q->where('name', 'Ahmad'))->firstOrFail();
        $groupQuotation = Quotation::where('customer_id', $ahmad->id)->firstOrFail();
        $this->assertSame(2, $groupQuotation->quotationItems()->where('is_header', false)->count());

        // Deposit invoice fully paid; balance invoice outstanding 4000.
        $deposit = Invoice::where('amount', 6000)->firstOrFail();
        $balance = Invoice::where('amount', 4000)->where('order_id', $deposit->order_id)->firstOrFail();
        $this->assertSame(0.0, (float) $deposit->outstandingAmount);
        $this->assertSame(4000.0, (float) $balance->outstandingAmount);

        // Both installment invoices carry the full (2-member) item set via the pivot.
        $this->assertSame(
            $groupQuotation->quotationItems()->count(),
            $deposit->quotationItems()->count(),
        );

        // Ahmad + Siti share one sharing group (R1); Omar is in his own.
        $this->assertSame(2, ManifestSharingGroup::count());
        $r1 = ManifestMember::where('name', 'Ahmad')->firstOrFail();
        $r1b = ManifestMember::where('name', 'Siti')->firstOrFail();
        $this->assertNotNull($r1->manifest_sharing_group_id);
        $this->assertSame($r1->manifest_sharing_group_id, $r1b->manifest_sharing_group_id);

        // Regression guard for the Receipt-hook auto-link: NO duplicate manifest
        // members despite paid receipts on a seat-available, open package.
        $distinctCcm = ManifestMember::query()
            ->whereNotNull('customer_confirmation_member_id')
            ->distinct()
            ->count('customer_confirmation_member_id');
        $this->assertSame(3, $distinctCcm);
        $this->assertSame(3, ManifestMember::count());
    }

    // ── Scenario 2: minimal sheet => one confirmation per member ───────────────

    public function test_minimal_sheet_creates_one_confirmation_per_member(): void
    {
        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        $members = [
            $this->memberRow(['name' => 'Solo One', 'passport_number' => 'S1', 'sharing_plan' => 'single']),
            $this->memberRow(['name' => 'Solo Two', 'passport_number' => 'S2', 'sharing_plan' => 'double']),
        ];

        $result = $this->service()->importFromPayload($manifest, [], $members, []);

        $this->assertSame([], $result['errors'], json_encode($result['errors']));
        $this->assertSame(2, $result['imported_members']);
        $this->assertSame(2, $result['bookings']);
        $this->assertSame(2, CustomerConfirmation::count());
        $this->assertSame(2, Quotation::count());
        $this->assertSame(2, Invoice::count());
        $this->assertSame(0, Receipt::count());

        // No payments => each invoice equals the package price for its plan, outstanding.
        $single = Invoice::where('amount', 4000)->firstOrFail();
        $this->assertSame(4000.0, (float) $single->outstandingAmount);
    }

    // ── Scenario 3: reconciliation hard-fail rejects the whole booking ─────────

    public function test_installment_mismatch_hard_fails_booking_without_writes(): void
    {
        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        // Single member, plan double => quotation total 5000, but installments sum to 4000.
        $members = [
            $this->memberRow([
                'member_key' => 'M1', 'booking_ref' => 'B1', 'name' => 'Mismatch',
                'passport_number' => 'X1', 'sharing_plan' => 'double',
            ]),
        ];
        $payments = [
            ['booking_ref' => 'B1', 'payer_ref' => 'M1', 'installment_no' => 1, 'invoice_amount' => 4000,
                'invoice_date' => '2024-01-10', 'due_date' => '2024-01-10', 'paid_amount' => 4000,
                'paid_date' => '2024-01-10', 'payment_method' => 'cash', 'reference' => null],
        ];

        $result = $this->service()->importFromPayload($manifest, [], $members, $payments);

        $this->assertSame(0, $result['imported_members']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('does not match quotation total', $result['errors'][0]['message']);

        // Nothing was written for the rejected booking.
        $this->assertSame(0, CustomerConfirmation::count());
        $this->assertSame(0, Quotation::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, ManifestMember::count());
    }

    // ── Scenario 4: second import must not wipe the first (Risk A regression) ───

    public function test_second_import_preserves_first_batch(): void
    {
        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        $first = [$this->memberRow(['name' => 'First Pax', 'passport_number' => 'F1', 'sharing_plan' => 'single'])];
        $this->service()->importFromPayload($manifest, [], $first, []);

        $this->assertSame(1, ManifestMember::where('manifest_id', $manifest->id)->count());

        $second = [$this->memberRow(['name' => 'Second Pax', 'passport_number' => 'F2', 'sharing_plan' => 'double'])];
        $result = $this->service()->importFromPayload($manifest, [], $second, []);

        $this->assertSame([], $result['errors'], json_encode($result['errors']));
        // Both batches coexist.
        $this->assertSame(2, ManifestMember::where('manifest_id', $manifest->id)->count());
        $this->assertNotNull(ManifestMember::where('name', 'First Pax')->first());
        $this->assertNotNull(ManifestMember::where('name', 'Second Pax')->first());
    }

    // ── Scenario 5: existing customer matched by passport is reused ────────────

    public function test_existing_customer_is_matched_by_passport(): void
    {
        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        $existingUser = User::factory()->create(['name' => 'Reused Person']);
        $existing = Customer::create([
            'user_id' => $existingUser->id,
            'customer_number' => 'CUST-REUSE-1',
            'passport_number' => 'REUSE1',
        ]);

        $customerCountBefore = Customer::count();

        $members = [$this->memberRow([
            'name' => 'Reused Person', 'passport_number' => 'REUSE1', 'sharing_plan' => 'single',
        ])];

        $result = $this->service()->importFromPayload($manifest, [], $members, []);

        $this->assertSame([], $result['errors'], json_encode($result['errors']));
        // No new customer created — the passport match reused the existing one.
        $this->assertSame($customerCountBefore, Customer::count());

        $member = ManifestMember::where('name', 'Reused Person')->firstOrFail();
        $this->assertNotNull($member->customer_confirmation_member_id);
        $this->assertSame(
            $existing->id,
            $member->confirmationMember->customer_id,
        );
    }

    // ── Scenario 6: paying more than billed hard-fails the booking ─────────────

    public function test_overpayment_hard_fails_booking(): void
    {
        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        // double => billed 5000, installment matches, but paid 6000 (> billed).
        $members = [
            $this->memberRow([
                'member_key' => 'M1', 'booking_ref' => 'B1', 'name' => 'Overpayer',
                'passport_number' => 'O1', 'sharing_plan' => 'double',
            ]),
        ];
        $payments = [
            ['booking_ref' => 'B1', 'payer_ref' => 'M1', 'installment_no' => 1, 'invoice_amount' => 5000,
                'invoice_date' => '2024-01-10', 'due_date' => '2024-01-10', 'paid_amount' => 6000,
                'paid_date' => '2024-01-10', 'payment_method' => 'cash', 'reference' => null],
        ];

        $result = $this->service()->importFromPayload($manifest, [], $members, $payments);

        $this->assertSame(0, $result['imported_members']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('exceeds the billed total', $result['errors'][0]['message']);
        $this->assertSame(0, Receipt::count());
        $this->assertSame(0, ManifestMember::count());
    }

    // ── Scenario 7: manifest import feature flag ────────────────────────────────

    public function test_import_returns_403_when_feature_flag_is_disabled(): void
    {
        config(['manifest.import_enabled' => false]);

        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        $this->post(route('manifests.import', ['id' => $manifest->id]), [
            'members' => [
                $this->memberRow(['name' => 'Test', 'sharing_plan' => 'single']),
            ],
        ])->assertStatus(403);
    }

    public function test_import_allowed_when_feature_flag_is_enabled(): void
    {
        config(['manifest.import_enabled' => true]);

        $package = $this->makePackage();
        $manifest = $this->makeManifest($package);

        $this->post(route('manifests.import', ['id' => $manifest->id]), [
            'members' => [
                $this->memberRow(['name' => 'Test', 'sharing_plan' => 'single']),
            ],
        ])->assertRedirect();
    }
}
