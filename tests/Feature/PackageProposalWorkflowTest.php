<?php

namespace Tests\Feature;

use App\Enums\PackageProposalStatus;
use App\Models\Country;
use App\Models\Package;
use App\Models\PackageProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PackageProposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $salesUser;

    protected Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'package-proposal view',
            'package-proposal create',
            'package-proposal edit',
            'package-proposal delete',
            'package-proposal approve',
            'package view',
            'package create',
            'package edit',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superadminRole = Role::findOrCreate('superadmin', 'web');
        $superadminRole->givePermissionTo($permissions);

        $salesRole = Role::findOrCreate('sales', 'web');
        $salesRole->givePermissionTo([
            'package-proposal view',
            'package-proposal create',
            'package-proposal edit',
            'package-proposal delete',
        ]);

        $this->country = Country::create([
            'name' => 'Singapore',
            'adjective' => 'Singaporean',
            'currency_symbol' => 'SGD',
        ]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');

        $this->salesUser = User::factory()->create();
        $this->salesUser->assignRole('sales');
    }

    private function proposalPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Package PnL',
            'country_id' => $this->country->id,
            'currency_symbol' => 'SGD',
            'total_seats' => 40,
            'departure_date' => '2026-07-01',
            'return_date' => '2026-07-15',
            'price_single' => 5000,
            'price_double' => 4000,
            'price_triple' => 3500,
            'price_quad' => 3000,
            'child_with_bed_price' => 2500,
            'child_no_bed_price' => 2000,
            'infant_price' => 500,
            'expenditure' => [
                [
                    'title' => 'Flight Costs',
                    'sort_order' => 1,
                    'items' => [
                        ['item_name' => 'Airline Tickets', 'unit_price' => 800, 'quantity' => 40, 'sort_order' => 1],
                    ],
                    'extensions' => [],
                ],
            ],
            'officials' => [
                ['type' => 'mutawif', 'name' => 'John Doe'],
            ],
            'remarks' => 'Test proposal',
        ], $overrides);
    }

    public function test_superadmin_can_create_proposal(): void
    {
        $this->actingAs($this->superadmin);

        $response = $this->post(route('package-proposals.store'), $this->proposalPayload());

        $response->assertRedirect(route('package-proposals.index'));

        $this->assertDatabaseHas('package_proposals', [
            'name' => 'Test Package PnL',
            'status' => 'draft',
            'country_id' => $this->country->id,
            'total_seats' => 40,
        ]);
    }

    public function test_sales_can_create_proposal(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->post(route('package-proposals.store'), $this->proposalPayload());

        $response->assertRedirect(route('package-proposals.index'));

        $proposal = PackageProposal::first();
        $this->assertNotNull($proposal);
        $this->assertSame($this->salesUser->id, $proposal->created_by);
    }

    public function test_proposal_stores_expenditure_and_officials(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload());

        $proposal = PackageProposal::first();
        $this->assertCount(1, $proposal->expenditure);
        $this->assertSame('Flight Costs', $proposal->expenditure[0]['title']);
        $this->assertCount(1, $proposal->officials);
        $this->assertSame('mutawif', $proposal->officials[0]['type']);
    }

    public function test_submit_for_approval_transitions_status_and_notifies(): void
    {
        $this->actingAs($this->salesUser);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $response = $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $response->assertRedirect(route('package-proposals.show', $proposal->id));

        $proposal->refresh();
        $this->assertSame(PackageProposalStatus::PendingApproval, $proposal->status);
        $this->assertNotNull($proposal->submitted_at);
        $this->assertContains($this->superadmin->id, $proposal->approver_user_ids);

        $this->assertDatabaseHas('notifications', [
            'title' => 'Package PnL Approval Required',
            'exclusive' => true,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->superadmin->id,
        ]);
    }

    public function test_only_draft_proposals_can_be_submitted(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $response = $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_superadmin_approver_can_approve(): void
    {
        $this->actingAs($this->salesUser);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $this->actingAs($this->superadmin);

        $response = $this->post(route('package-proposals.approve', $proposal->id));

        $response->assertRedirect(route('package-proposals.show', $proposal->id));

        $proposal->refresh();
        $this->assertSame(PackageProposalStatus::Approved, $proposal->status);
        $this->assertSame($this->superadmin->id, $proposal->approved_rejected_by);
    }

    public function test_non_selected_superadmin_cannot_approve(): void
    {
        $otherSuperadmin = User::factory()->create();
        $otherSuperadmin->assignRole('superadmin');

        $this->actingAs($this->salesUser);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $this->actingAs($otherSuperadmin);

        $response = $this->post(route('package-proposals.approve', $proposal->id));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_reject_with_reason(): void
    {
        $this->actingAs($this->salesUser);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $this->actingAs($this->superadmin);

        $response = $this->post(route('package-proposals.reject', $proposal->id), [
            'rejection_reason' => 'Budget too high',
        ]);

        $response->assertRedirect(route('package-proposals.show', $proposal->id));

        $proposal->refresh();
        $this->assertSame(PackageProposalStatus::Rejected, $proposal->status);
        $this->assertSame('Budget too high', $proposal->rejection_reason);
    }

    public function test_rejected_proposal_reverts_to_draft_on_update(): void
    {
        $this->actingAs($this->salesUser);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $this->actingAs($this->superadmin);
        $this->post(route('package-proposals.reject', $proposal->id), [
            'rejection_reason' => 'Too expensive',
        ]);

        $this->actingAs($this->salesUser);
        $response = $this->put(route('package-proposals.update', $proposal->id), $this->proposalPayload([
            'name' => 'Revised Proposal',
            'price_double' => 3500,
        ]));

        $response->assertRedirect(route('package-proposals.index'));

        $proposal->refresh();
        $this->assertSame(PackageProposalStatus::Draft, $proposal->status);
        $this->assertSame('Revised Proposal', $proposal->name);
        $this->assertNull($proposal->rejection_reason);
    }

    public function test_create_package_from_approved_proposal(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $this->post(route('package-proposals.approve', $proposal->id));

        $response = $this->post(route('package-proposals.create-package', $proposal->id));

        $package = Package::latest()->first();
        $this->assertNotNull($package);

        $response->assertRedirect(route('packages.edit', $package->id));

        $proposal->refresh();
        $this->assertSame($package->id, $proposal->package_id);

        $this->assertSame($proposal->name, $package->name);
        $this->assertSame($proposal->country_id, $package->country_id);
        $this->assertEquals($proposal->price_double, $package->price_double);
        $this->assertSame($proposal->total_seats, $package->total_seats);
    }

    public function test_cannot_create_duplicate_package_from_proposal(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);
        $this->post(route('package-proposals.approve', $proposal->id));
        $this->post(route('package-proposals.create-package', $proposal->id));

        $response = $this->post(route('package-proposals.create-package', $proposal->id));
        $response->assertStatus(422);
    }

    public function test_only_draft_proposals_can_be_deleted(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $this->post(route('package-proposals.submit', $proposal->id), [
            'approver_user_ids' => [$this->superadmin->id],
        ]);

        $response = $this->delete(route('package-proposals.destroy', $proposal->id));
        $response->assertStatus(422);
    }

    public function test_draft_proposal_can_be_deleted(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload());
        $proposal = PackageProposal::first();

        $response = $this->delete(route('package-proposals.destroy', $proposal->id));
        $response->assertRedirect(route('package-proposals.index'));

        $this->assertDatabaseMissing('package_proposals', ['id' => $proposal->id]);
    }

    public function test_proposal_number_auto_generated(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload());

        $proposal = PackageProposal::first();
        $this->assertNotNull($proposal->proposal_number);
        $this->assertStringStartsWith('PNL-', $proposal->proposal_number);
    }

    public function test_currency_symbol_snapshotted_from_country(): void
    {
        $this->actingAs($this->superadmin);

        $this->post(route('package-proposals.store'), $this->proposalPayload([
            'currency_symbol' => null,
        ]));

        $proposal = PackageProposal::first();
        $this->assertSame('SGD', $proposal->currency_symbol);
    }
}
