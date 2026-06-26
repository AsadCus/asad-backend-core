<?php

namespace Tests\Feature;

use App\Models\BusinessTrip;
use App\Models\BusinessTripReportItem;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessTripApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    private function makeEmployeeUser(string $role, array $attrs = []): array
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $employee = Employee::query()->create(array_merge([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
        ], $attrs));

        return [$user, $employee];
    }

    private function payload(): array
    {
        return [
            'work_type' => 'operational',
            'project_name' => 'AQMS Maintenance',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'destination_address' => 'PT ACSI, Bandung',
            'depart_at' => '2026-06-21 08:00:00',
            'return_at' => '2026-06-22 18:00:00',
            'bank' => 'BANK MANDIRI',
            'account_no' => '1640005660255',
            'account_holder' => 'John Doe',
            'cost_breakdown' => [
                ['title' => 'General', 'items' => [
                    ['description' => 'Uang Makan', 'cost' => 75000, 'qty' => 2, 'unit' => 'Makan'],
                ]],
            ],
        ];
    }

    public function test_full_business_trip_workflow(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $hcUser = User::factory()->create();
        $hcUser->assignRole('hr');
        $financeUser = User::factory()->create();
        $financeUser->assignRole('finance');
        $financeUser->givePermissionTo(['hris.business-trip view-all', 'hris.business-trip approve-finance', 'hris.business-trip pay']);

        // 1. Employee submits.
        $this->actingAs($empUser, 'sanctum');
        $create = $this->postJson('/api/business-trips', $this->payload())
            ->assertCreated()
            ->assertJsonFragment(['status' => 'Pending Leader', 'grand_total' => 150000]);
        $id = $create->json('id');

        // 2. Leader (supervisor) approves → pending HC.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-leader")
            ->assertOk()->assertJsonFragment(['status' => 'Pending HC']);

        // 3. HC approves → pending Finance.
        $this->actingAs($hcUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-hc")
            ->assertOk()->assertJsonFragment(['status' => 'Pending Finance']);

        // 4. Finance approves → approved.
        $this->actingAs($financeUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-finance")
            ->assertOk()->assertJsonFragment(['status' => 'Approved']);

        // 5. Finance disburses.
        $this->postJson("/api/business-trips/{$id}/pay")
            ->assertOk()->assertJsonFragment(['payment_status' => 'paid']);

        // 6. Employee submits the multi-ledger report — income matches the disbursed amount,
        // and the one expense line is fully receipt-backed.
        Storage::fake('public');
        $this->actingAs($empUser, 'sanctum');
        $report = $this->post("/api/business-trips/{$id}/report", [
            'items' => [
                ['category' => 'income', 'date' => '2026-06-21', 'description' => 'Uang muka perjalanan dinas', 'amount' => 150000],
                [
                    'category' => 'expense', 'date' => '2026-06-21', 'description' => 'Uang makan',
                    'kategori' => 'Konsumsi', 'amount' => 150000,
                    'attachment' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
                ],
            ],
        ])->assertOk();
        $this->assertSame(0, $report->json('variance'));
        $this->assertSame(100, $report->json('report_percentage'));
        $this->assertSame('Pending Leader', $report->json('report_status'));

        // 7. The report has its own Leader → Finance approval cycle, separate from disbursement.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/report/approve-leader")
            ->assertOk()->assertJsonFragment(['report_status' => 'Pending Finance']);

        $this->actingAs($financeUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/report/approve-finance")
            ->assertOk()->assertJsonFragment(['report_status' => 'Approved']);

        // 8. Finance confirms the (zero) leftover variance is settled.
        $this->postJson("/api/business-trips/{$id}/settle")
            ->assertOk()->assertJsonFragment(['balance_settled' => true]);

        $trip = BusinessTrip::query()->with('reportItems')->findOrFail($id);
        $this->assertCount(2, $trip->reportItems);
        Storage::disk('public')->assertExists($trip->reportItems->firstWhere('category', 'expense')->attachment_path);
    }

    public function test_report_leader_can_reject_and_employee_can_resubmit(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $hcUser = User::factory()->create();
        $hcUser->assignRole('hr');
        $financeUser = User::factory()->create();
        $financeUser->assignRole('finance');
        $financeUser->givePermissionTo(['hris.business-trip approve-finance', 'hris.business-trip pay']);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/business-trips', $this->payload())->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-leader")->assertOk();
        $this->actingAs($hcUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-hc")->assertOk();
        $this->actingAs($financeUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-finance")->assertOk();
        $this->postJson("/api/business-trips/{$id}/pay")->assertOk();

        // First report: only spent half the disbursed amount, leaving an unexplained variance.
        $this->actingAs($empUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/report", [
            'items' => [
                ['category' => 'income', 'date' => '2026-06-21', 'description' => 'Uang muka', 'amount' => 150000],
                ['category' => 'expense', 'date' => '2026-06-21', 'description' => 'Uang makan', 'amount' => 75000],
            ],
        ])->assertOk()->assertJsonFragment(['variance' => 75000]);

        // Leader rejects it (e.g. asks for the missing receipt for the other half).
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/report/reject", ['note' => 'Missing the rest of the spend'])
            ->assertOk()->assertJsonFragment(['report_status' => 'Rejected']);

        // Employee resubmits with the corrected, fully-accounted-for breakdown — this replaces
        // the previous line items wholesale and restarts the approval cycle.
        $this->actingAs($empUser, 'sanctum');
        $resubmit = $this->postJson("/api/business-trips/{$id}/report", [
            'items' => [
                ['category' => 'income', 'date' => '2026-06-21', 'description' => 'Uang muka', 'amount' => 150000],
                ['category' => 'expense', 'date' => '2026-06-21', 'description' => 'Uang makan', 'amount' => 150000],
            ],
        ])->assertOk();
        $this->assertSame(0, $resubmit->json('variance'));
        $this->assertSame('Pending Leader', $resubmit->json('report_status'));

        $trip = BusinessTrip::query()->with('reportItems')->findOrFail($id);
        $this->assertCount(2, $trip->reportItems);
    }

    public function test_resubmitting_a_report_preserves_an_unchanged_items_receipt(): void
    {
        Storage::fake('public');
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/business-trips', $this->payload())->json('id');
        $trip = BusinessTrip::query()->findOrFail($id);
        $trip->update(['status' => 'approved', 'payment_status' => 'paid']);

        $first = $this->post("/api/business-trips/{$id}/report", [
            'items' => [[
                'category' => 'expense', 'date' => '2026-06-21', 'description' => 'Uang makan',
                'amount' => 75000, 'attachment' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
            ]],
        ])->assertOk();
        $this->assertSame(100, $first->json('report_percentage'));
        $storedPath = BusinessTripReportItem::query()->where('business_trip_id', $id)->first()->attachment_path;
        Storage::disk('public')->assertExists($storedPath);

        // Resubmit the same item without picking a new file, but echoing back its stored path —
        // the receipt must not be lost just because the row was replaced wholesale.
        $second = $this->postJson("/api/business-trips/{$id}/report", [
            'items' => [[
                'category' => 'expense', 'date' => '2026-06-21', 'description' => 'Uang makan (revisi)',
                'amount' => 75000, 'attachment_path' => $storedPath,
            ]],
        ])->assertOk();
        $this->assertSame(100, $second->json('report_percentage'));
        $this->assertSame(
            $storedPath,
            BusinessTripReportItem::query()->where('business_trip_id', $id)->first()->attachment_path,
        );
    }

    public function test_leader_can_reject(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/business-trips', $this->payload())->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/reject", ['note' => 'Not eligible'])
            ->assertOk()->assertJsonFragment(['status' => 'Rejected']);
    }

    public function test_owner_can_cancel_while_pending(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/business-trips', $this->payload())->json('id');

        $this->postJson("/api/business-trips/{$id}/cancel")
            ->assertOk()->assertJsonFragment(['status' => 'Cancelled']);
    }

    public function test_employee_only_sees_own_trips(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        [$otherUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($otherUser, 'sanctum');
        $this->postJson('/api/business-trips', $this->payload())->assertCreated();

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/business-trips', $this->payload())->assertCreated();

        $this->getJson('/api/business-trips')->assertOk()->assertJsonCount(1);
    }

    public function test_employee_cannot_approve(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/business-trips', $this->payload())->json('id');

        $this->postJson("/api/business-trips/{$id}/approve-leader")->assertStatus(403);
    }

    public function test_so_reference_required_when_work_type_is_so(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $this->actingAs($empUser, 'sanctum');

        $payload = array_merge($this->payload(), ['work_type' => 'so']);
        $this->postJson('/api/business-trips', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('so_reference');
    }

    public function test_only_the_assigned_leader_or_admin_can_approve_the_leader_stage(): void
    {
        [, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        [$otherSupUser] = $this->makeEmployeeUser('supervisor'); // has approve-leader, but not this trip's leader

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/business-trips', $this->payload())->json('id');

        // A different supervisor cannot approve a trip that isn't theirs.
        $this->actingAs($otherSupUser, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-leader")->assertStatus(403);

        // The administrator escape hatch can act on any stage.
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $this->actingAs($admin, 'sanctum');
        $this->postJson("/api/business-trips/{$id}/approve-leader")
            ->assertOk()->assertJsonFragment(['status' => 'Pending HC']);
    }

    public function test_trip_without_a_supervisor_skips_the_leader_stage(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee'); // no supervisor_id

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/business-trips', $this->payload())
            ->assertCreated()
            ->assertJsonFragment(['status' => 'Pending HC']);
    }

    public function test_submitting_notifies_the_assigned_leader(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/business-trips', $this->payload())->assertCreated();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $supUser->id]);
    }

    public function test_my_returns_only_own_business_trips(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        [, $otherEmployee] = $this->makeEmployeeUser('employee');

        BusinessTrip::factory()->count(2)->create(['employee_id' => $employee->id]);
        BusinessTrip::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->actingAs($empUser, 'sanctum');
        $this->getJson('/api/business-trips/my')
            ->assertOk()
            ->assertJsonCount(2);
    }
}
