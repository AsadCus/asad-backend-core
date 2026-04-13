<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ModelFile;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageAccommodation;
use App\Models\PackageOfficial;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemTax;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManifestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_persists_manifest_documents_arabic_names_and_ignores_receipt_documents_in_full_submit(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Yusuf Adam',
            'contact' => '0191111111',
        ]);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-DOC-001',
            'name' => 'Umrah Documents',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'draft',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Yusuf Adam',
                    'arabic_name' => 'يوسف ادم',
                    'receipt_documents' => [
                        [
                            'file' => UploadedFile::fake()->create('receipt-proof.pdf', 100, 'application/pdf'),
                            'file_name' => 'Member Receipt Proof.pdf',
                        ],
                    ],
                ],
            ],
            'documents' => [
                'train_tickets' => [],
                'flight_tickets' => [
                    [
                        'file' => UploadedFile::fake()->create('flight-ticket.pdf', 120, 'application/pdf'),
                        'file_name' => 'Flight Ticket.pdf',
                    ],
                ],
                'visa' => [],
                'hotel' => [],
                'passport' => [],
                'photo' => [],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::query()->firstOrFail();
        $member = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->firstOrFail();

        $this->assertSame('يوسف ادم', $member->arabic_name);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'flight_tickets',
            'file_name' => 'Flight Ticket.pdf',
        ]);

        $this->assertDatabaseMissing('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $member->id,
            'field' => 'receipt',
        ]);

        $storedManifestFilePath = ModelFile::query()
            ->where('fileable_type', Manifest::class)
            ->where('fileable_id', $manifest->id)
            ->where('field', 'flight_tickets')
            ->value('file_path');

        $this->assertNotNull($storedManifestFilePath);
        Storage::disk('public')->assertExists((string) $storedManifestFilePath);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertNotEmpty($rehydrated['documents']['flight_tickets'] ?? []);
        $this->assertSame('يوسف ادم', $rehydrated['members'][0]['arabic_name'] ?? null);
        $this->assertEmpty($rehydrated['members'][0]['receipt_documents'] ?? []);
    }

    public function test_store_update_with_stay_on_form_persists_manifest_member_receipts_and_redirects_to_requested_tab(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Yusuf Adam',
            'contact' => '0191111111',
        ]);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-DOC-002',
            'name' => 'Umrah Documents Update',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $confirmationMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'draft',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-DOC-002',
            'status' => 'draft',
        ]);

        $manifestMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $confirmationMember->id,
            'name' => 'Yusuf Adam',
            'sort_order' => 1,
        ]);

        $payload = [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'id' => $manifestMember->id,
                    'customer_confirmation_member_id' => $confirmationMember->id,
                    'name_as_per_passport' => 'Yusuf Adam',
                    'arabic_name' => 'يوسف ادم',
                ],
            ],
            'manifest_member_receipts' => [
                [
                    'manifest_member_id' => $manifestMember->id,
                    'customer_confirmation_member_id' => $confirmationMember->id,
                    'receipt_documents' => [
                        [
                            'file' => UploadedFile::fake()->create('receipt-proof.pdf', 100, 'application/pdf'),
                            'file_name' => 'Member Receipt Proof.pdf',
                        ],
                    ],
                ],
            ],
            'documents' => [
                'train_tickets' => [],
                'flight_tickets' => [],
                'visa' => [],
                'hotel' => [],
                'passport' => [],
                'photo' => [],
            ],
        ];

        $this->post(route('manifests.store', ['stay_on_form' => 1, 'tab' => 'receipt']), $payload)
            ->assertRedirect(route('manifests.edit', ['manifest' => $manifest->id, 'tab' => 'receipt']));

        $manifestMember->refresh();

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $manifestMember->id,
            'field' => 'receipt',
            'file_name' => 'Member Receipt Proof.pdf',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $memberRow = collect($rehydrated['members'])
            ->firstWhere('id', $manifestMember->id);

        $this->assertNotNull($memberRow);
        $this->assertNotEmpty($memberRow['receipt_documents'] ?? []);
    }

    public function test_store_accepts_grouped_manifest_payload_and_normalizes_values(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Ahmad Example',
            'contact' => '0199988877',
        ]);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-001',
            'name' => 'Umrah Basic',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'passport_number' => 'A1234567',
            'date_of_birth' => '1995-01-10',
            'address' => 'Jalan Bukit Bintang, Kuala Lumpur',
            'first_time_umrah' => true,
            'has_chronic_disease' => false,
            'passport_path' => 'passports/customer-001.pdf',
            'photo_path' => 'photos/customer-001.jpg',
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'draft',
            'relationship' => 'spouse',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'sn' => 1,
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Ahmad Example',
                    'passport_number' => 'A9999999',
                    'date_of_birth' => '1996-02-20',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'name_as_per_passport' => 'Ahmad Example',
                        'customer_confirmation_member_id' => $member->id,
                        'room_relationship' => 'Family',
                        'room_number' => 'M-101',
                        'room_type' => 'Quad',
                        'bed_type' => 'Single',
                        'meal' => 'Breakfast Only',
                        'room_remarks' => 'Room-level note',
                        'remarks' => 'Member-level note',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::first();

        $this->assertNotNull($manifest);
        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Ahmad Example',
            'contact_number' => '0199988877',
            'passport_number' => 'A9999999',
            'address' => 'Jalan Bukit Bintang, Kuala Lumpur',
            'first_time_umrah' => 1,
            'has_chronic_disease' => 0,
            'passport_path' => 'passports/customer-001.pdf',
            'photo_path' => 'photos/customer-001.jpg',
        ]);

        $manifestMemberDob = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('date_of_birth');

        $this->assertNotNull($manifestMemberDob);

        $customer->refresh();
        $this->assertSame('A9999999', $customer->passport_number);
        $this->assertSame('1996-02-20', optional($customer->date_of_birth)->format('Y-m-d'));

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'group_relationship' => 'Family',
            'room_number' => 'M-101',
            'room_type' => 'quad',
            'bed_type' => 'single',
            'meal' => 'Breakfast Only',
            'remarks' => 'Room-level note',
        ]);

        $createdMemberId = (int) ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('id');

        $createdRoomId = (int) $manifest->rooms()->value('id');

        $this->assertDatabaseHas('manifest_room_members', [
            'manifest_room_id' => $createdRoomId,
            'manifest_member_id' => $createdMemberId,
            'sort_order' => 1,
            'remarks' => 'Member-level note',
        ]);
    }

    public function test_store_accepts_canonical_submit_payload_with_legacy_parity(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Canonical Member',
            'contact' => '0112233445',
        ]);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-CANONICAL-POST-001',
            'name' => 'Umrah Canonical Submit',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'passport_number' => 'P445566',
            'date_of_birth' => '1992-03-11',
            'address' => 'Jalan Ampang, Kuala Lumpur',
            'first_time_umrah' => true,
            'has_chronic_disease' => false,
            'passport_path' => 'passports/canonical.pdf',
            'photo_path' => 'photos/canonical.jpg',
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'relationship' => 'spouse',
            'sharing_plan' => 'double',
        ]);

        $legacyPayload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'notes' => 'legacy payload',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Canonical Member',
                    'passport_number' => 'P998877',
                    'date_of_birth' => '1993-04-21',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-legacy-1',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $member->id,
                        'name_as_per_passport' => 'Canonical Member',
                        'sharing_group_key' => 'group-legacy-1',
                        'room_relationship' => 'Family',
                        'room_label' => 'Legacy Room',
                        'room_number' => 'LK-101',
                        'room_type' => 'double',
                        'bed_type' => 'queen',
                        'sharing_plan' => 'double',
                        'meal' => 'Breakfast Only',
                        'room_remarks' => 'Legacy room remark',
                    ],
                ],
            ],
            'documents' => [
                'train_tickets' => [],
                'flight_tickets' => [],
                'visa' => [],
                'hotel' => [],
                'passport' => [],
                'photo' => [],
            ],
        ];

        $this->post(route('manifests.store'), $legacyPayload)
            ->assertRedirect(route('manifests.index'));

        $legacyManifest = Manifest::query()->latest('id')->firstOrFail();

        $canonicalPayload = [
            'manifest' => [
                'package_id' => $package->id,
                'status' => 'draft',
                'notes' => 'canonical payload',
            ],
            'manifest_sharing_groups' => [
                [
                    'customer_confirmation_id' => $confirmation->id,
                    'sort_order' => 1,
                    'group_relationship' => 'Family',
                    'remarks' => 'Canonical group remark',
                    'members' => [
                        [
                            'customer_confirmation_member_id' => $member->id,
                            'relationship' => 'spouse',
                            'sharing_plan' => 'double',
                            'sort_order' => 1,
                            'patch' => [
                                'name_as_per_passport' => 'Canonical Member',
                                'passport_number' => 'P998877',
                                'date_of_birth' => '1993-04-21',
                            ],
                        ],
                    ],
                ],
            ],
            'manifest_rooms' => [
                [
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'relationship' => 'Family',
                    'room_label' => 'Canonical Room',
                    'room_number' => 'CN-101',
                    'room_type' => 'double',
                    'bed_type' => 'queen',
                    'sharing_plan' => 'double',
                    'meal' => 'Breakfast Only',
                    'remarks' => 'Canonical room remark',
                    'members' => [
                        [
                            'customer_confirmation_member_id' => $member->id,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
            'documents' => [
                'train_tickets' => [],
                'flight_tickets' => [],
                'visa' => [],
                'hotel' => [],
                'passport' => [],
                'photo' => [],
            ],
        ];

        $this->post(route('manifests.store'), $canonicalPayload)
            ->assertRedirect(route('manifests.index'));

        $canonicalManifest = Manifest::query()->latest('id')->firstOrFail();

        $this->assertNotSame($legacyManifest->id, $canonicalManifest->id);

        $legacyMember = ManifestMember::query()
            ->where('manifest_id', $legacyManifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->firstOrFail();

        $canonicalMember = ManifestMember::query()
            ->where('manifest_id', $canonicalManifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->firstOrFail();

        $this->assertSame($legacyMember->passport_number, $canonicalMember->passport_number);
        $this->assertSame(
            optional($legacyMember->date_of_birth)->format('Y-m-d'),
            optional($canonicalMember->date_of_birth)->format('Y-m-d'),
        );

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $canonicalManifest->id,
            'location' => 'makkah',
            'room_number' => 'CN-101',
            'room_type' => 'double',
            'bed_type' => 'queen',
            'sharing_plan' => null,
            'meal' => 'Breakfast Only',
            'remarks' => 'Canonical room remark',
        ]);

        $this->assertSame(1, $legacyManifest->members()->count());
        $this->assertSame(1, $canonicalManifest->members()->count());
        $this->assertSame(1, $legacyManifest->rooms()->count());
        $this->assertSame(1, $canonicalManifest->rooms()->count());
    }

    public function test_update_persists_collection_items_checklist_fields(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-COLLECTION-001',
            'name' => 'Umrah Collection Checklist',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-COLLECTION-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Checklist Member', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Checklist Member',
                    'course_1' => true,
                    'course_2' => false,
                    'lanyard' => true,
                    'luggage_tag' => true,
                    'cabin_tag' => false,
                    'passport_cover' => true,
                    'umrah_guidebook' => true,
                    'sling_bag' => false,
                    'cabin_size_luggage' => true,
                    'umrah_essentials' => true,
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $memberId = (int) ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('id');

        $this->assertDatabaseHas('manifest_member_collection_items', [
            'manifest_member_id' => $memberId,
            'course_1' => 1,
            'course_2' => 0,
            'lanyard' => 1,
            'luggage_tag' => 1,
            'cabin_tag' => 0,
            'passport_cover' => 1,
            'umrah_guidebook' => 1,
            'sling_bag' => 0,
            'cabin_size_luggage' => 1,
            'umrah_essentials' => 1,
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertTrue((bool) ($rehydrated['members'][0]['course_1'] ?? false));
        $this->assertTrue((bool) ($rehydrated['members'][0]['luggage_tag'] ?? false));
        $this->assertFalse((bool) ($rehydrated['members'][0]['cabin_tag'] ?? true));
    }

    public function test_update_preserves_collection_items_when_checklist_fields_are_omitted(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-COLLECTION-KEEP-001',
            'name' => 'Umrah Collection Keep',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-COLLECTION-KEEP-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Checklist Keep Member', $actingUser->id);

        $firstSubmitPayload = [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Checklist Keep Member',
                    'course_1' => true,
                    'course_2' => true,
                    'lanyard' => true,
                    'luggage_tag' => false,
                ],
            ],
        ];

        $this->post(route('manifests.store'), $firstSubmitPayload)
            ->assertRedirect(route('manifests.index'));

        $secondSubmitPayload = [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Checklist Keep Member',
                ],
            ],
        ];

        $this->post(route('manifests.store'), $secondSubmitPayload)
            ->assertRedirect(route('manifests.index'));

        $memberId = (int) ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('id');

        $this->assertDatabaseHas('manifest_member_collection_items', [
            'manifest_member_id' => $memberId,
            'course_1' => 1,
            'course_2' => 1,
            'lanyard' => 1,
            'luggage_tag' => 0,
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertTrue((bool) ($rehydrated['members'][0]['course_1'] ?? false));
        $this->assertTrue((bool) ($rehydrated['members'][0]['course_2'] ?? false));
        $this->assertTrue((bool) ($rehydrated['members'][0]['lanyard'] ?? false));
        $this->assertFalse((bool) ($rehydrated['members'][0]['luggage_tag'] ?? true));
    }

    public function test_update_preserves_receipt_files_when_member_receipt_payload_is_omitted(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-RECEIPT-KEEP-001',
            'name' => 'Umrah Receipt Keep',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-RECEIPT-KEEP-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Receipt Keep Member', $actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Receipt Keep Member',
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $initialMemberId = (int) ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('id');

        $this->patch(route('manifests.sections.receipt-documents.update', ['manifestId' => $manifest->id]), [
            'manifest_member_receipts' => [
                (string) $initialMemberId => [
                    [
                        'file' => UploadedFile::fake()->create('member-receipt.pdf', 100, 'application/pdf'),
                        'file_name' => 'Member Receipt Keep.pdf',
                    ],
                ],
            ],
        ])->assertOk();

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Receipt Keep Member',
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $updatedMember = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->firstOrFail();

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $updatedMember->id,
            'field' => 'receipt',
            'file_name' => 'Member Receipt Keep.pdf',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertCount(1, $rehydrated['members'][0]['receipt_documents'] ?? []);
        $this->assertSame('Member Receipt Keep.pdf', $rehydrated['members'][0]['receipt_documents'][0]['file_name'] ?? null);
    }

    public function test_patch_documents_section_does_not_wipe_namelist_collection_values(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'manifest_member' => $member] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $manifest->package_id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Section Fixture Member',
                    'course_1' => true,
                    'course_2' => false,
                    'lanyard' => true,
                    'luggage_tag' => true,
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $this->patch(route('manifests.sections.documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'documents' => [
                'train_tickets' => [],
                'flight_tickets' => [
                    [
                        'file' => UploadedFile::fake()->create('c2-docs-proof.pdf', 100, 'application/pdf'),
                        'file_name' => 'C2 Docs Proof.pdf',
                    ],
                ],
                'visa' => [],
                'hotel' => [],
                'passport' => [],
                'photo' => [],
            ],
        ])->assertOk();

        $memberId = (int) ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('id');

        $this->assertDatabaseHas('manifest_member_collection_items', [
            'manifest_member_id' => $memberId,
            'course_1' => 1,
            'course_2' => 0,
            'lanyard' => 1,
            'luggage_tag' => 1,
        ]);
    }

    public function test_patch_rooms_section_does_not_wipe_receipt_files(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patch(route('manifests.sections.receipt-documents.update', ['manifestId' => $manifest->id]), [
            'manifest_member_receipts' => [
                (string) $manifestMember->id => [
                    [
                        'file' => UploadedFile::fake()->create('c2-rooms-receipt.pdf', 100, 'application/pdf'),
                        'file_name' => 'C2 Rooms Receipt.pdf',
                    ],
                ],
            ],
        ])->assertOk();

        $this->patchJson(route('manifests.sections.rooms.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_rooms' => [
                [
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'relationship' => 'Family',
                    'room_label' => 'C2 Room',
                    'room_number' => 'C2-101',
                    'room_type' => 'double',
                    'bed_type' => 'king',
                    'sharing_plan' => 'double',
                    'meal' => 'Breakfast',
                    'members' => [
                        [
                            'manifest_member_id' => $manifestMember->id,
                            'customer_confirmation_member_id' => $member->id,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $refreshedMember = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->firstOrFail();

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $refreshedMember->id,
            'field' => 'receipt',
            'file_name' => 'C2 Rooms Receipt.pdf',
        ]);
    }

    public function test_manifest_status_updates_package_status_and_rehydrates_from_package(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-STATUS-LINK-001',
            'name' => 'Umrah Status Link',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-STATUS-LINK-001',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'closed',
            'members' => [],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $package->refresh();
        $this->assertSame('closed', $package->status);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $this->assertSame('closed', $rehydrated['status']);
    }

    public function test_get_for_edit_show_returns_grouped_members_shape(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-002',
            'name' => 'Umrah Premium',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-2002',
            'status' => 'draft',
        ]);

        $customerUser = User::factory()->create(['name' => 'Siti Example']);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'date_of_birth' => '1990-01-01',
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => User::factory()->create()->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'confirmed',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);

        $service = app(ManifestService::class);
        $result = $service->getForEditShow($manifest->id);

        $this->assertIsArray($result['members']);
        $this->assertNotEmpty($result['members']);

        $this->assertSame('Siti Example', $result['members'][0]['name_as_per_passport']);
        $this->assertArrayHasKey('roomLists', $result);
        $this->assertArrayHasKey('airlineList', $result);
        $this->assertArrayHasKey('manifest', $result);
        $this->assertArrayHasKey('manifest_sharing_groups', $result);
        $this->assertArrayHasKey('manifest_rooms', $result);
        $this->assertArrayHasKey('documents', $result);

        $this->assertSame($result['id'], $result['manifest']['id']);
        $this->assertSame($result['package_id'], $result['manifest']['package_id']);
        $this->assertSame($result['manifest_number'], $result['manifest']['manifest_number']);
        $this->assertCount(count($result['sharing_groups']), $result['manifest_sharing_groups']);
        $this->assertCount(count($result['rooms']), $result['manifest_rooms']);
    }

    public function test_update_persists_and_rehydrates_manifest_sharing_group_remarks(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-GROUP-REMARK-001',
            'name' => 'Umrah Group Remark',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-GROUP-REMARK-001',
            'status' => 'draft',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Remark One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Remark Two', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'sn' => 1,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Remark One',
                    'sharing_group_key' => 'group-remarks-a',
                    'sharing_plan' => 'double',
                    'group_remarks' => 'Shared group note from main tab',
                ],
                [
                    'sn' => 2,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Remark Two',
                    'sharing_group_key' => 'group-remarks-a',
                    'sharing_plan' => 'double',
                    'group_remarks' => 'Shared group note from main tab',
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();

        $this->assertDatabaseHas('manifest_sharing_groups', [
            'manifest_id' => $manifest->id,
            'remarks' => 'Shared group note from main tab',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $this->assertSame(
            'Shared group note from main tab',
            $rehydrated['members'][0]['group_remarks'] ?? null,
        );
    }

    public function test_room_grouping_falls_back_to_each_customer_when_no_sharing_group_exists(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-003',
            'name' => 'Umrah Dynamic',
            'status' => 'open',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Member One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Member Two', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'sn' => 1,
                    'name_as_per_passport' => 'Member One',
                ],
                [
                    'sn' => 2,
                    'name_as_per_passport' => 'Member Two',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $memberOne->id,
                        'name_as_per_passport' => 'Member One',
                        'room_number' => 'M-201',
                        'room_type' => 'Double',
                    ],
                    [
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'name_as_per_passport' => 'Member Two',
                        'room_number' => 'M-202',
                        'room_type' => 'Double',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::firstOrFail();

        $this->assertSame(2, $manifest->rooms()->count());
    }

    public function test_room_list_order_can_be_different_between_hotels_and_is_persisted(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-004',
            'name' => 'Umrah Multi Hotel',
            'status' => 'open',
        ]);

        $memberA = $this->createMemberForPackage($package->id, 'Member A', $actingUser->id);
        $memberB = $this->createMemberForPackage($package->id, 'Member B', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'sn' => 1,
                    'name_as_per_passport' => 'Member A',
                ],
                [
                    'sn' => 2,
                    'name_as_per_passport' => 'Member B',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $memberA->id,
                        'name_as_per_passport' => 'Member A',
                        'room_number' => 'MK-01',
                        'sort_order' => 1,
                    ],
                    [
                        'customer_confirmation_member_id' => $memberB->id,
                        'name_as_per_passport' => 'Member B',
                        'room_number' => 'MK-02',
                        'sort_order' => 2,
                    ],
                ],
                'madinah' => [
                    [
                        'customer_confirmation_member_id' => $memberB->id,
                        'name_as_per_passport' => 'Member B',
                        'room_number' => 'MD-01',
                        'sort_order' => 1,
                    ],
                    [
                        'customer_confirmation_member_id' => $memberA->id,
                        'name_as_per_passport' => 'Member A',
                        'room_number' => 'MD-02',
                        'sort_order' => 2,
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::firstOrFail();

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'room_number' => 'MK-01',
        ]);

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'madinah',
            'room_number' => 'MD-01',
        ]);
    }

    public function test_update_persists_multi_location_room_edits_and_non_receipt_document_tabs(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-DOC-EDIT-001',
            'name' => 'Manifest Edit Room + Docs',
            'status' => 'open',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Member Room One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Member Room Two', $actingUser->id);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-DOC-EDIT-001',
            'status' => 'draft',
        ]);

        $payload = [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Member Room One',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-makkah-1',
                ],
                [
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Member Room Two',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-madinah-1',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $memberOne->id,
                        'name_as_per_passport' => 'Member Room One',
                        'sharing_group_key' => 'group-makkah-1',
                        'room_relationship' => 'Family',
                        'room_label' => 'Makkah Room A',
                        'room_number' => 'MK-901',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'sharing_plan' => 'double',
                        'meal' => 'Breakfast Only',
                    ],
                ],
                'madinah' => [
                    [
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'name_as_per_passport' => 'Member Room Two',
                        'sharing_group_key' => 'group-madinah-1',
                        'room_relationship' => 'Friends',
                        'room_label' => 'Madinah Room B',
                        'room_number' => 'MD-902',
                        'room_type' => 'triple',
                        'bed_type' => 'single',
                        'sharing_plan' => 'triple',
                        'meal' => 'Full Board',
                    ],
                ],
            ],
            'documents' => [
                'train_tickets' => [
                    [
                        'file' => UploadedFile::fake()->create('train-update.pdf', 120, 'application/pdf'),
                        'file_name' => 'Train Update.pdf',
                    ],
                ],
                'flight_tickets' => [
                    [
                        'file' => UploadedFile::fake()->create('flight-update.pdf', 120, 'application/pdf'),
                        'file_name' => 'Flight Update.pdf',
                    ],
                ],
                'visa' => [
                    [
                        'file' => UploadedFile::fake()->create('visa-update.pdf', 120, 'application/pdf'),
                        'file_name' => 'Visa Update.pdf',
                    ],
                ],
                'hotel' => [
                    [
                        'file' => UploadedFile::fake()->create('hotel-update.pdf', 120, 'application/pdf'),
                        'file_name' => 'Hotel Update.pdf',
                    ],
                ],
                'passport' => [
                    [
                        'file' => UploadedFile::fake()->create('passport-update.pdf', 120, 'application/pdf'),
                        'file_name' => 'Passport Update.pdf',
                    ],
                ],
                'photo' => [
                    [
                        'file' => UploadedFile::fake()->create('photo-update.jpg', 120, 'image/jpeg'),
                        'file_name' => 'Photo Update.jpg',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'group_relationship' => 'Family',
            'room_label' => 'Makkah Room A',
            'room_number' => 'MK-901',
            'room_type' => 'double',
            'bed_type' => 'king',
            'sharing_plan' => null,
            'meal' => 'Breakfast Only',
        ]);

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'madinah',
            'group_relationship' => 'Friends',
            'room_label' => 'Madinah Room B',
            'room_number' => 'MD-902',
            'room_type' => 'triple',
            'bed_type' => 'single',
            'sharing_plan' => null,
            'meal' => 'Full Board',
        ]);

        foreach (['train_tickets', 'flight_tickets', 'visa', 'hotel'] as $field) {
            $this->assertDatabaseHas('model_files', [
                'fileable_type' => Manifest::class,
                'fileable_id' => $manifest->id,
                'field' => $field,
            ]);
        }

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertNotEmpty($rehydrated['roomLists']['makkah'] ?? []);
        $this->assertNotEmpty($rehydrated['roomLists']['madinah'] ?? []);
        $this->assertNotEmpty($rehydrated['documents']['train_tickets'] ?? []);
        $this->assertNotEmpty($rehydrated['documents']['flight_tickets'] ?? []);
        $this->assertNotEmpty($rehydrated['documents']['visa'] ?? []);
        $this->assertNotEmpty($rehydrated['documents']['hotel'] ?? []);
        $this->assertEmpty($rehydrated['documents']['passport'] ?? []);
        $this->assertEmpty($rehydrated['documents']['photo'] ?? []);
    }

    public function test_store_rejects_customer_confirmation_member_from_different_package(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create();

        $this->actingAs($actingUser);

        $manifestPackage = Package::create([
            'package_number' => 'PKG-050',
            'name' => 'Umrah Package A',
            'status' => 'open',
        ]);

        $differentPackage = Package::create([
            'package_number' => 'PKG-051',
            'name' => 'Umrah Package B',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $differentPackage->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'draft',
        ]);

        $payload = [
            'package_id' => $manifestPackage->id,
            'status' => 'draft',
            'members' => [
                [
                    'sn' => 1,
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Mismatch Member',
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertSessionHasErrors('members');

        $this->assertSame(0, Manifest::count());
    }

    public function test_update_room_members_uses_current_manifest_member_ids_after_reorder(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-UPDATE',
            'name' => 'Room Update Package',
            'status' => 'open',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Member One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Member Two', $actingUser->id);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-UPDATE',
            'status' => 'draft',
        ]);

        $memberOne = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
        ]);

        $memberTwo = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberTwo->id,
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'id' => $memberOne->id,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Member One',
                ],
                [
                    'id' => $memberTwo->id,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Member Two',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'id' => $memberOne->id,
                        'manifest_member_id' => $memberOne->id,
                        'customer_confirmation_member_id' => $memberOne->id,
                        'sharing_group_key' => 'group-1',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'RM-1',
                    ],
                    [
                        'id' => $memberTwo->id,
                        'manifest_member_id' => $memberTwo->id,
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'sharing_group_key' => 'group-1',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'RM-1',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();
        $currentMemberIds = $manifest->members()->pluck('id')->all();

        $this->assertCount(2, $currentMemberIds);
        $this->assertDatabaseCount('manifest_room_members', 2);

        foreach ($currentMemberIds as $memberId) {
            $this->assertDatabaseHas('manifest_room_members', [
                'manifest_member_id' => $memberId,
            ]);
        }
    }

    public function test_manifest_member_can_be_moved_to_holding_confirmation(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create();

        $this->actingAs($actingUser);

        $sourcePackage = Package::create([
            'package_number' => 'PKG-HOLD-001',
            'name' => 'Umrah Holding Source',
            'status' => 'open',
        ]);

        $targetPackage = Package::create([
            'package_number' => 'PKG-HOLD-002',
            'name' => 'Umrah Holding Target',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $sourcePackage->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
        ]);

        $manifest = Manifest::create([
            'package_id' => $sourcePackage->id,
            'manifest_number' => 'MNF-HOLD-001',
            'status' => 'confirmed',
        ]);

        $manifestMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);

        $this->postJson(route('manifests.members.move-holding', [
            'manifestId' => $manifest->id,
            'memberId' => $manifestMember->id,
        ]), [
            'target_package_id' => $targetPackage->id,
        ])->assertOk();

        $this->assertDatabaseMissing('manifest_members', [
            'id' => $manifestMember->id,
        ]);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'cancelled',
        ]);

        $newConfirmation = CustomerConfirmation::query()
            ->where('id', '!=', $confirmation->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($newConfirmation);
        $this->assertSame($targetPackage->id, $newConfirmation->package_id);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'customer_confirmation_id' => $newConfirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_update_allows_room_number_to_be_empty_from_room_lists(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-NULL-001',
            'name' => 'Umrah Nullable Room Number',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-NULL-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Nullable Room Member', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'sn' => 1,
                    'name_as_per_passport' => 'Nullable Room Member',
                    'customer_confirmation_member_id' => $member->id,
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'sn' => 1,
                        'name_as_per_passport' => 'Nullable Room Member',
                        'customer_confirmation_member_id' => $member->id,
                        'room_number' => null,
                        'room_type' => 'double',
                        'bed_type' => null,
                        'capacity' => 3,
                        'sharing_group_key' => 'group-1',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'room_number' => null,
            'room_type' => 'double',
        ]);
    }

    public function test_update_persists_regrouped_room_lists_and_rehydrates_group_keys(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-REGROUP-001',
            'name' => 'Umrah Regroup Persist',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-REGROUP-001',
            'status' => 'draft',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Regroup One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Regroup Two', $actingUser->id);
        $memberThree = $this->createMemberForPackage($package->id, 'Regroup Three', $actingUser->id);

        $memberOne = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
        ]);
        $memberTwo = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberTwo->id,
        ]);
        $memberThree = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberThree->id,
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'id' => $memberOne->id,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Regroup One',
                ],
                [
                    'id' => $memberTwo->id,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Regroup Two',
                ],
                [
                    'id' => $memberThree->id,
                    'customer_confirmation_member_id' => $memberThree->id,
                    'name_as_per_passport' => 'Regroup Three',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'manifest_member_id' => $memberOne->id,
                        'customer_confirmation_member_id' => $memberOne->id,
                        'name_as_per_passport' => 'Regroup One',
                        'sharing_group_key' => 'room-double-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room 1',
                        'room_number' => 'MK-501',
                        'sort_order' => 1,
                    ],
                    [
                        'manifest_member_id' => $memberTwo->id,
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'name_as_per_passport' => 'Regroup Two',
                        'sharing_group_key' => 'room-double-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room 1',
                        'room_number' => 'MK-501',
                        'sort_order' => 2,
                    ],
                    [
                        'manifest_member_id' => $memberThree->id,
                        'customer_confirmation_member_id' => $memberThree->id,
                        'name_as_per_passport' => 'Regroup Three',
                        'sharing_group_key' => 'room-single-b',
                        'sharing_plan' => 'single',
                        'room_type' => 'single',
                        'bed_type' => 'single',
                        'room_label' => 'Room 2',
                        'room_number' => 'MK-502',
                        'sort_order' => 1,
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();
        $manifest->load(['rooms.roomMembers']);

        $this->assertCount(2, $manifest->rooms);
        $this->assertSame([1, 2], $manifest->rooms->pluck('roomMembers')->map->count()->sort()->values()->all());

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $roomRows = $rehydrated['roomLists']['makkah'] ?? [];

        $this->assertCount(3, $roomRows);
        $this->assertSame(2, collect($roomRows)->pluck('sharing_group_key')->filter()->unique()->count());
    }

    public function test_room_member_sharing_plan_and_room_relationship_are_synced_on_update(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-SHARING-001',
            'name' => 'Umrah Room Sharing Sync',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-SHARING-001',
            'status' => 'draft',
        ]);

        $confirmationMember = $this->createMemberForPackage($package->id, 'Sharing Member', $actingUser->id);
        $confirmationMember->update([
            'sharing_plan' => 'single',
            'relationship' => 'Spouse',
        ]);

        $manifestMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $confirmationMember->id,
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'id' => $manifestMember->id,
                    'customer_confirmation_member_id' => $confirmationMember->id,
                    'name_as_per_passport' => 'Sharing Member',
                    'sharing_plan' => 'double',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'manifest_member_id' => $manifestMember->id,
                        'customer_confirmation_member_id' => $confirmationMember->id,
                        'name_as_per_passport' => 'Sharing Member',
                        'sharing_group_key' => 'room-relationship-a',
                        'room_relationship' => 'Family',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room A',
                        'room_number' => 'MK-700',
                        'sort_order' => 1,
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $confirmationMember->refresh();

        $this->assertSame('single', $confirmationMember->sharing_plan);
        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'group_relationship' => 'Family',
            'sharing_plan' => null,
            'room_label' => 'Room A',
        ]);
    }

    public function test_relationship_update_does_not_override_member_role(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROLE-REL-001',
            'name' => 'Role Relation Package',
            'status' => 'open',
        ]);

        $customerUser = User::factory()->create([
            'name' => 'Member Role Relation',
            'contact' => '0198877665',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'confirmed',
            'relationship' => 'wife',
            'sharing_plan' => 'double',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROLE-REL-001',
            'status' => 'draft',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Member Role Relation',
                    'group_relationship' => 'family',
                    'sharing_group_key' => 'group-role-rel-1',
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $member->refresh();
        $manifest->refresh();

        $this->assertSame('wife', $member->relationship);
        $this->assertSame('family', $manifest->manifestSharingGroups()->value('group_relationship'));
    }

    public function test_update_adds_new_member_into_existing_confirmation_room_capacity_before_creating_new_group(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-CAPACITY-001',
            'name' => 'Umrah Capacity Assignment',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $members = collect();

        foreach (range(1, 4) as $index) {
            $user = User::factory()->create(['name' => 'Capacity Member '.$index]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            $members->push(CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $index === 1,
                'status' => 'confirmed',
                'relationship' => 'member',
                'sharing_plan' => 'double',
            ]));
        }

        $first = $members->get(0);
        $second = $members->get(1);
        $third = $members->get(2);
        $fourth = $members->get(3);

        $this->post(route('manifests.store'), [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $first->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Capacity Member 1',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-double-a',
                ],
                [
                    'customer_confirmation_member_id' => $second->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Capacity Member 2',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-double-a',
                ],
                [
                    'customer_confirmation_member_id' => $third->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Capacity Member 3',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-double-b',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $first->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Capacity Member 1',
                        'sharing_plan' => 'double',
                        'sharing_group_key' => 'room-double-a',
                        'room_type' => 'double',
                    ],
                    [
                        'customer_confirmation_member_id' => $second->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Capacity Member 2',
                        'sharing_plan' => 'double',
                        'sharing_group_key' => 'room-double-a',
                        'room_type' => 'double',
                    ],
                    [
                        'customer_confirmation_member_id' => $third->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Capacity Member 3',
                        'sharing_plan' => 'double',
                        'sharing_group_key' => 'room-double-b',
                        'room_type' => 'double',
                    ],
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $manifest = Manifest::query()->firstOrFail();
        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);

        $payload['members'][] = [
            'customer_confirmation_member_id' => $fourth->id,
            'customer_confirmation_id' => $confirmation->id,
            'name_as_per_passport' => 'Capacity Member 4',
            'sharing_plan' => 'double',
            'sharing_group_key' => 'solo-'.$fourth->id,
        ];

        $payload['roomLists']['makkah'][] = [
            'customer_confirmation_member_id' => $fourth->id,
            'customer_confirmation_id' => $confirmation->id,
            'name_as_per_passport' => 'Capacity Member 4',
            'sharing_plan' => 'double',
            'sharing_group_key' => 'solo-'.$fourth->id,
            'room_type' => 'double',
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();

        $groupMemberCounts = $manifest->manifestSharingGroups()
            ->where('customer_confirmation_id', $confirmation->id)
            ->withCount('members')
            ->orderBy('id')
            ->get()
            ->pluck('members_count')
            ->sort()
            ->values()
            ->all();

        $roomMemberCounts = $manifest->rooms()
            ->withCount('roomMembers')
            ->orderBy('id')
            ->get()
            ->pluck('room_members_count')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([2, 2], $groupMemberCounts);
        $this->assertSame([1, 2], $roomMemberCounts);
    }

    public function test_update_keeps_new_confirmation_members_separate_from_official_group_and_assigns_rooms_per_location(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-MIXED-GROUP-001',
            'name' => 'Mixed Group Isolation',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Member One', $actingUser->id, $confirmation->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Member Two', $actingUser->id, $confirmation->id);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'name' => 'Official One',
            'type' => 'mutawwif',
            'passport_number' => 'OFF-MIX-001',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-MIXED-GROUP-001',
            'status' => 'draft',
        ]);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'package_official_id' => $official->id,
                    'name_as_per_passport' => 'Official One',
                    'sharing_group_key' => 'shared-main-group',
                    'sharing_plan' => 'double',
                    'group_sort_order' => 1,
                ],
                [
                    'customer_confirmation_member_id' => $memberOne->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Member One',
                    'sharing_group_key' => 'shared-main-group',
                    'sharing_plan' => 'double',
                    'group_sort_order' => 2,
                ],
                [
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Member Two',
                    'sharing_group_key' => 'shared-main-group',
                    'sharing_plan' => 'double',
                    'group_sort_order' => 2,
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $memberOne->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member One',
                        'sharing_group_key' => 'room-shared-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'MK-001',
                    ],
                    [
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member Two',
                        'sharing_group_key' => 'room-shared-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'MK-001',
                    ],
                    [
                        'package_official_id' => $official->id,
                        'name_as_per_passport' => 'Official One',
                        'sharing_group_key' => 'room-shared-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'MK-001',
                    ],
                ],
                'madinah' => [
                    [
                        'customer_confirmation_member_id' => $memberOne->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member One',
                        'sharing_group_key' => 'room-shared-b',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'MD-001',
                    ],
                    [
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member Two',
                        'sharing_group_key' => 'room-shared-b',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'MD-001',
                    ],
                    [
                        'package_official_id' => $official->id,
                        'name_as_per_passport' => 'Official One',
                        'sharing_group_key' => 'room-shared-b',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_number' => 'MD-001',
                    ],
                ],
            ],
        ], (int) $manifest->id);

        $manifest->refresh();

        $groups = $manifest->manifestSharingGroups()
            ->withCount('members')
            ->orderBy('sort_order')
            ->get();

        $this->assertSame([1, 2], $groups->pluck('members_count')->sort()->values()->all());
        $this->assertSame(1, $groups->whereNotNull('customer_confirmation_id')->count());
    }

    public function test_update_persists_group_member_sort_order_and_keeps_official_groups_last(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ORDER-001',
            'name' => 'Ordering Package',
            'status' => 'open',
        ]);

        $customerUser = User::factory()->create(['name' => 'Sorted Member']);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'relationship' => 'member',
            'sharing_plan' => 'double',
        ]);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'name' => 'Official Member',
            'type' => 'mutawwif',
            'passport_number' => 'OFF-0001',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ORDER-001',
            'status' => 'draft',
        ]);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'package_official_id' => $official->id,
                    'name_as_per_passport' => 'Official Member',
                    'sharing_group_key' => 'official-group',
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                ],
                [
                    'customer_confirmation_member_id' => $member->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Sorted Member',
                    'sharing_group_key' => 'member-group',
                    'group_sort_order' => 2,
                    'sort_order' => 1,
                ],
            ],
        ], (int) $manifest->id);

        $manifest->refresh();
        $orderedGroups = $manifest->manifestSharingGroups()
            ->orderBy('sort_order')
            ->with('members')
            ->get();

        $this->assertCount(2, $orderedGroups);
        $this->assertNotNull($orderedGroups[0]->customer_confirmation_id);
        $this->assertNull($orderedGroups[1]->customer_confirmation_id);

        $result = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $members = $result['members'];

        $this->assertCount(2, $members);
        $this->assertSame($member->id, $members[0]['customer_confirmation_member_id']);
        $this->assertSame($official->id, $members[1]['package_official_id']);
        $this->assertSame(1, (int) ($members[0]['group_sort_order'] ?? 0));
        $this->assertSame(2, (int) ($members[1]['group_sort_order'] ?? 0));
    }

    public function test_update_keeps_member_order_stable_inside_same_sharing_group_across_repeated_updates(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-MEMBER-ORDER-001',
            'name' => 'Member Order Stability',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'triple',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $makeMember = function (string $name) use ($confirmation): CustomerConfirmationMember {
            $user = User::factory()->create(['name' => $name]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'status' => 'confirmed',
                'relationship' => 'member',
                'sharing_plan' => 'triple',
            ]);
        };

        $memberA = $makeMember('Order A');
        $memberB = $makeMember('Order B');
        $memberC = $makeMember('Order C');

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-MEMBER-ORDER-001',
            'status' => 'draft',
        ]);

        $sharedGroupKey = 'stable-group-1';

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $memberA->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Order A',
                    'sharing_group_key' => $sharedGroupKey,
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                ],
                [
                    'customer_confirmation_member_id' => $memberB->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Order B',
                    'sharing_group_key' => $sharedGroupKey,
                    'group_sort_order' => 1,
                    'sort_order' => 2,
                ],
                [
                    'customer_confirmation_member_id' => $memberC->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Order C',
                    'sharing_group_key' => $sharedGroupKey,
                    'group_sort_order' => 1,
                    'sort_order' => 3,
                ],
            ],
        ], (int) $manifest->id);

        $service = app(ManifestService::class);
        $payload = $service->getForEditShow((int) $manifest->id);

        $this->assertSame($memberA->id, $payload['members'][0]['customer_confirmation_member_id']);
        $this->assertSame($memberB->id, $payload['members'][1]['customer_confirmation_member_id']);
        $this->assertSame($memberC->id, $payload['members'][2]['customer_confirmation_member_id']);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => $payload['members'],
        ], (int) $manifest->id);

        $payloadAfterSecondUpdate = $service->getForEditShow((int) $manifest->id);

        $this->assertSame($memberA->id, $payloadAfterSecondUpdate['members'][0]['customer_confirmation_member_id']);
        $this->assertSame($memberB->id, $payloadAfterSecondUpdate['members'][1]['customer_confirmation_member_id']);
        $this->assertSame($memberC->id, $payloadAfterSecondUpdate['members'][2]['customer_confirmation_member_id']);
    }

    public function test_update_removes_members_missing_from_room_list_and_drops_empty_rooms(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-UNASSIGN-001',
            'name' => 'Room Unassign Cleanup',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-UNASSIGN-001',
            'status' => 'draft',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Room Keep One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Room Remove Two', $actingUser->id);
        $memberThree = $this->createMemberForPackage($package->id, 'Room Remove Three', $actingUser->id);

        $memberOne = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
            'name' => 'Room Keep One',
        ]);

        $memberTwo = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberTwo->id,
            'name' => 'Room Remove Two',
        ]);

        $memberThree = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberThree->id,
            'name' => 'Room Remove Three',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'id' => $memberOne->id,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Room Keep One',
                    'sharing_plan' => 'double',
                ],
                [
                    'id' => $memberTwo->id,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Room Remove Two',
                    'sharing_plan' => 'double',
                ],
                [
                    'id' => $memberThree->id,
                    'customer_confirmation_member_id' => $memberThree->id,
                    'name_as_per_passport' => 'Room Remove Three',
                    'sharing_plan' => 'single',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'manifest_member_id' => $memberOne->id,
                        'customer_confirmation_member_id' => $memberOne->id,
                        'name_as_per_passport' => 'Room Keep One',
                        'sharing_group_key' => 'room-keep-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room Keep',
                        'room_number' => 'MK-801',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();
        $manifest->load(['rooms.roomMembers', 'members']);

        $this->assertCount(1, $manifest->rooms);
        $this->assertSame('MK-801', $manifest->rooms[0]->room_number);
        $this->assertCount(1, $manifest->rooms[0]->roomMembers);

        $persistedMemberOneId = (int) $manifest->members()
            ->where('customer_confirmation_member_id', $memberOne->id)
            ->value('id');

        $this->assertSame($persistedMemberOneId, (int) $manifest->rooms[0]->roomMembers[0]->manifest_member_id);
    }

    public function test_collection_items_pdf_export_returns_pdf_response(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-COLLECTION-PDF-001',
            'name' => 'Collection PDF Package',
            'status' => 'open',
            'departure_date' => '2026-03-20',
            'return_date' => '2026-03-30',
        ]);

        PackageAccommodation::create([
            'package_id' => $package->id,
            'location' => 'Makkah',
            'hotel_name' => 'Hotel One',
            'check_in' => '2026-03-21',
            'check_out' => '2026-03-25',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-COLLECTION-PDF-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Collection Pdf Member', $actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Collection Pdf Member',
                    'course_1' => true,
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $response = $this->get(route('manifests.collection-items-pdf', $manifest->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_arabic_names_pdf_export_returns_pdf_response(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ARABIC-PDF-001',
            'name' => 'Arabic PDF Package',
            'status' => 'open',
            'departure_date' => '2026-05-01',
            'return_date' => '2026-05-11',
        ]);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'name' => 'Official One',
            'contact_number' => '0191234567',
            'sort_order' => 1,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'in_charge_official_id' => $official->id,
            'manifest_number' => 'MAN-ARABIC-PDF-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Arabic Pdf Member', $actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'in_charge_official_id' => $official->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Arabic Pdf Member',
                    'arabic_name' => 'عربي عضو',
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $response = $this->get(route('manifests.arabic-names-pdf', $manifest->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_airline_names_pdf_export_returns_pdf_response(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-AIRLINE-PDF-001',
            'name' => 'Airline PDF Package',
            'status' => 'open',
            'departure_date' => '2026-05-01',
            'return_date' => '2026-05-11',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-AIRLINE-PDF-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Airline Pdf Member', $actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Airline Pdf Member',
                    'passport_number' => 'A123456789',
                    'nationality' => 'Singaporean',
                    'gender' => 'male',
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $response = $this->get(route('manifests.airline-names-pdf', $manifest->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_room_check_pdf_export_returns_pdf_response_for_location(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-CHECK-PDF-001',
            'name' => 'Room Check PDF Package',
            'status' => 'open',
            'departure_date' => '2026-04-01',
            'return_date' => '2026-04-10',
        ]);

        PackageAccommodation::create([
            'package_id' => $package->id,
            'location' => 'Makkah',
            'hotel_name' => 'Hotel Check',
            'check_in' => '2026-04-02',
            'check_out' => '2026-04-06',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-CHECK-PDF-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Room Check Member', $actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Room Check Member',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'room-check-group-1',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $member->id,
                        'name_as_per_passport' => 'Room Check Member',
                        'sharing_group_key' => 'room-check-group-1',
                        'room_relationship' => 'Family',
                        'room_label' => 'Room 1',
                        'room_number' => 'MK-401',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'number_of_beds_checked' => true,
                        'meal' => 'Breakfast Only',
                        'remarks' => 'Member note',
                        'room_remarks' => 'Room note',
                    ],
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $response = $this->get(route('manifests.room-check-pdf', [
            'id' => $manifest->id,
            'location' => 'makkah',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_get_for_edit_show_fills_financial_columns_from_receipts_and_discount_extensions(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-001',
            'name' => 'Manifest Financial Snapshot',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-001',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        $manifestMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $quotation->update([
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => -300,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package installment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit invoice',
            'extensions' => [
                [
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 1000,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 1000,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second invoice',
            'extensions' => [
                [
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 1500,
            'invoice_date' => '2026-03-10',
            'due_date' => '2026-03-10',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 1500,
            'receipt_date' => '2026-03-10',
            'payment_method' => 'transfer',
        ]);

        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Final invoice',
            'extensions' => [
                [
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 500,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-03-20',
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $thirdInvoice->id,
            'amount' => 500,
            'receipt_date' => '2026-03-20',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(300.0, (float) ($memberRow['discount'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-01')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_deposit_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-10')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_second_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['second_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-20')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_third_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['third_payment'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_applies_member_discount_to_payment_columns_sequentially_with_spillover(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-DISC-PAY-001',
            'name' => 'Manifest Discount Payment Bucket Package',
            'status' => 'open',
            'price_double' => 9000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-DISC-PAY-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $members = collect([
            ['name' => 'Payment Discount Member One', 'sharing_plan' => 'double'],
            ['name' => 'Payment Discount Member Two', 'sharing_plan' => 'double'],
        ])->map(function (array $row) use ($confirmation): CustomerConfirmationMember {
            $user = User::factory()->create(['name' => $row['name']]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => false,
                'status' => 'confirmed',
                'relationship' => 'member',
                'sharing_plan' => $row['sharing_plan'],
            ]);
        })->values();

        foreach ($members as $index => $member) {
            ManifestMember::create([
                'manifest_id' => $manifest->id,
                'customer_confirmation_member_id' => $member->id,
                'sharing_plan' => $member->sharing_plan,
                'sort_order' => $index + 1,
            ]);
        }

        $quotation = Quotation::create([
            'customer_id' => (int) $members[0]->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => '2026-05-01',
            'expiry_date' => '2026-05-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $quotationItems = collect([
            ['member_id' => $members[0]->id, 'rate' => 9000],
            ['member_id' => $members[1]->id, 'rate' => 9000],
        ])->map(function (array $row, int $index) use ($quotation): QuotationItem {
            return QuotationItem::create([
                'quotation_id' => $quotation->id,
                'customer_confirmation_member_id' => $row['member_id'],
                'description' => 'Discount payment member #'.($index + 1),
                'is_header' => false,
                'quantity' => 1,
                'rate' => $row['rate'],
                'sort_order' => $index + 1,
            ]);
        });

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit invoice',
            'extensions' => [
                [
                    'name' => 'First Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 1000,
                    'amount' => -1000,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 1000,
            'invoice_date' => '2026-05-01',
            'due_date' => '2026-05-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 1000,
            'receipt_date' => '2026-05-01',
            'payment_method' => 'transfer',
        ]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second invoice',
            'extensions' => [
                [
                    'name' => 'Second Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 6000,
                    'amount' => -6000,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 9000,
            'invoice_date' => '2026-05-10',
            'due_date' => '2026-05-10',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 9000,
            'receipt_date' => '2026-05-10',
            'payment_method' => 'transfer',
        ]);

        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Final invoice',
            'extensions' => [
                [
                    'name' => 'Third Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 3000,
                    'amount' => -3000,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 8000,
            'invoice_date' => '2026-05-20',
            'due_date' => '2026-05-20',
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $thirdInvoice->id,
            'amount' => 8000,
            'receipt_date' => '2026-05-20',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $memberRows = collect($rehydrated['members'])->keyBy('customer_confirmation_member_id');

        $this->assertSame(9000.0, (float) ($memberRows[$members[0]->id]['discount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRows[$members[0]->id]['balance_due'] ?? 0));

        $this->assertSame(1000.0, (float) ($memberRows[$members[1]->id]['discount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRows[$members[1]->id]['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_applies_second_invoice_discount_to_second_payment_stage_only(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-STAGE-002',
            'name' => 'Manifest Discount Stage Mapping Package',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-STAGE-002',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Discount Stage Member', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-06-01',
            'expiry_date' => '2026-06-30',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Installment package line',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $createInvoice = function (string $description, string $date, array $extensions = []) use ($order, $quotationItem): void {
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'description' => $description,
                'extensions' => $extensions,
                'amount' => 5000,
                'invoice_date' => $date,
                'due_date' => $date,
                'status' => 'issued',
            ]);

            $invoice->quotationItems()->sync([$quotationItem->id]);

            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 5000,
                'receipt_date' => $date,
                'payment_method' => 'transfer',
            ]);
        };

        $createInvoice('Deposit invoice', '2026-06-01');
        $createInvoice('Second invoice', '2026-06-10', [
            [
                'name' => 'Second Stage Discount',
                'type' => 'discount',
                'calculation_mode' => 'fixed',
                'calculation_value' => 300,
                'amount' => -300,
                'sort_order' => 1,
            ],
        ]);
        $createInvoice('Third invoice', '2026-06-20');

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(300.0, (float) ($memberRow['discount'] ?? 0));
        $this->assertSame(5000.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertSame(4700.0, (float) ($memberRow['second_payment'] ?? 0));
        $this->assertSame(5000.0, (float) ($memberRow['third_payment'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_accumulates_third_payment_from_third_invoice_and_later(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-003',
            'name' => 'Manifest Financial Third Bucket',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-003',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Third Bucket', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
            'extensions' => [
                [
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => -300,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package installment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $createInvoice = function (string $description, float $amount, string $invoiceDate) use ($order, $quotationItem) {
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'description' => $description,
                'extensions' => [
                    [
                        'name' => 'Promo Discount',
                        'type' => 'discount',
                        'calculation_mode' => 'fixed',
                        'calculation_value' => 100,
                        'amount' => -100,
                        'sort_order' => 1,
                    ],
                ],
                'amount' => $amount,
                'invoice_date' => $invoiceDate,
                'due_date' => $invoiceDate,
                'status' => 'issued',
            ]);

            $invoice->quotationItems()->sync([$quotationItem->id]);

            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'receipt_date' => $invoiceDate,
                'payment_method' => 'transfer',
            ]);
        };

        $createInvoice('Deposit invoice', 1000, '2026-03-01');
        $createInvoice('Second invoice', 1500, '2026-03-10');
        $createInvoice('Third invoice', 500, '2026-03-20');
        $createInvoice('Fourth invoice', 200, '2026-03-25');

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(400.0, (float) ($memberRow['discount'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-01')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_deposit_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-10')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_second_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['second_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-20')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_third_payment'] ?? ''),
        );
        $this->assertSame(9800.0, (float) ($memberRow['third_payment'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_payment_buckets_exclude_positive_invoice_extensions_when_invoice_marked_paid_without_receipts(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-004',
            'name' => 'Manifest Financial Paid Fallback Extension',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-004',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Fallback Extension', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package full payment',
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
            'description' => 'Paid invoice no receipt',
            'extensions' => [
                [
                    'name' => 'Admin Fee',
                    'type' => 'tax',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => 300,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 5300,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertNull($memberRow['second_payment']);
        $this->assertNull($memberRow['third_payment']);
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_payment_buckets_exclude_positive_invoice_extensions_for_receipt_totals(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-005',
            'name' => 'Manifest Financial Receipt Extension Exclusion',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-005',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Receipt Extension', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package full payment',
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
            'description' => 'Issued invoice with tax extension',
            'extensions' => [
                [
                    'name' => 'Admin Fee',
                    'type' => 'tax',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => 300,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 5300,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 5300,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertNull($memberRow['second_payment']);
        $this->assertNull($memberRow['third_payment']);
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_payment_buckets_include_negative_item_discount_extensions(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-005A',
            'name' => 'Manifest Financial Item Discount Inclusion',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-005A',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Item Discount', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package full payment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        QuotationItemTax::create([
            'quotation_item_id' => $quotationItem->id,
            'name' => 'Item Discount',
            'calculation_mode' => 'fixed',
            'calculation_value' => -300,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Issued invoice with item discount',
            'amount' => 4700,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 4700,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(300.0, (float) ($memberRow['discount'] ?? 0));
        $this->assertSame(4700.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertNull($memberRow['second_payment']);
        $this->assertNull($memberRow['third_payment']);
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_assigns_item_discount_to_item_owner_member_not_payer(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-005B',
            'name' => 'Manifest Financial Item Discount Ownership',
            'status' => 'open',
            'price_double' => 2500,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-005B',
        ]);

        $payerMember = $this->createMemberForPackage(
            $package->id,
            'Financial Payer Member',
            $actingUser->id,
        );

        $discountOwnerMember = $this->createMemberForPackage(
            $package->id,
            'Financial Discount Owner Member',
            $actingUser->id,
            $payerMember->customer_confirmation_id,
        );

        $payerMember->update(['sharing_plan' => 'double']);
        $discountOwnerMember->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $payerMember->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $discountOwnerMember->id,
            'sharing_plan' => 'double',
            'sort_order' => 2,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $payerMember->customer_id,
            'customer_confirmation_id' => $payerMember->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $payerItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $payerMember->id,
            'description' => 'Payer package item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 2500,
            'sort_order' => 1,
        ]);

        $discountOwnerItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $discountOwnerMember->id,
            'description' => 'Discount owner package item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 2500,
            'sort_order' => 2,
        ]);

        QuotationItemTax::create([
            'quotation_item_id' => $discountOwnerItem->id,
            'name' => 'Item Discount',
            'calculation_mode' => 'fixed',
            'calculation_value' => -300,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Issued invoice with member-specific item discount',
            'amount' => 4700,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$payerItem->id, $discountOwnerItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 4700,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $payerRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $payerMember->id);
        $discountOwnerRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $discountOwnerMember->id);

        $this->assertNotNull($payerRow);
        $this->assertNotNull($discountOwnerRow);
        $this->assertSame(0.0, (float) ($payerRow['discount'] ?? 0));
        $this->assertSame(2500.0, (float) ($payerRow['deposit_payment'] ?? 0));
        $this->assertSame(300.0, (float) ($discountOwnerRow['discount'] ?? 0));
        $this->assertSame(2200.0, (float) ($discountOwnerRow['deposit_payment'] ?? 0));
        $this->assertSame(0.0, (float) ($payerRow['balance_due'] ?? 0));
        $this->assertSame(0.0, (float) ($discountOwnerRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_maps_paid_amounts_to_receipt_date_sequence_slots(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-006',
            'name' => 'Manifest Financial Invoice Slot Mapping',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-006',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Slot Mapping', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package installment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit invoice unpaid',
            'amount' => 1000,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync([$quotationItem->id]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second invoice paid',
            'amount' => 1500,
            'invoice_date' => '2026-03-10',
            'due_date' => '2026-03-10',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 1500,
            'receipt_date' => '2026-03-10',
            'payment_method' => 'transfer',
        ]);

        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Third invoice paid',
            'amount' => 500,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-03-20',
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $thirdInvoice->id,
            'amount' => 500,
            'receipt_date' => '2026-03-20',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-10')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_deposit_payment'] ?? ''),
        );
        $this->assertSame(5000.0, (float) ($memberRow['second_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-20')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_second_payment'] ?? ''),
        );
        $this->assertNull($memberRow['third_payment']);
        $this->assertNull($memberRow['date_of_third_payment']);
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_includes_refund_invoice_in_payment_buckets_and_balance_due(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-006-R',
            'name' => 'Manifest Financial Refund Bucket',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-006-R',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Refund Bucket', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $packageItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package payment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $refundItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Refund adjustment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => -1000,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $paidInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Paid invoice',
            'amount' => 5000,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'paid',
        ]);
        $paidInvoice->quotationItems()->sync([$packageItem->id]);

        Receipt::create([
            'invoice_id' => $paidInvoice->id,
            'amount' => 5000,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $refundInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Refund invoice',
            'amount' => -1000,
            'invoice_date' => '2026-03-15',
            'due_date' => '2026-03-15',
            'status' => 'refund',
        ]);
        $refundInvoice->quotationItems()->sync([$refundItem->id]);

        Receipt::create([
            'invoice_id' => $refundInvoice->id,
            'amount' => -1000,
            'receipt_date' => '2026-03-15',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-01')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_deposit_payment'] ?? ''),
        );
        $this->assertSame(-1000.0, (float) ($memberRow['second_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-15')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_second_payment'] ?? ''),
        );
        $this->assertNull($memberRow['third_payment']);
        $this->assertNull($memberRow['date_of_third_payment']);
        $this->assertSame(1000.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_orders_payment_buckets_by_invoice_id_not_receipt_date(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-006-I',
            'name' => 'Manifest Financial Invoice Ordering',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-006-I',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Invoice Ordering', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $firstItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'First installment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 2000,
            'sort_order' => 1,
        ]);

        $secondItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Second installment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 3000,
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice #1',
            'amount' => 2000,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync([$firstItem->id]);

        Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 2000,
            'receipt_date' => '2026-03-20',
            'payment_method' => 'transfer',
        ]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice #2',
            'amount' => 3000,
            'invoice_date' => '2026-03-05',
            'due_date' => '2026-03-05',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync([$secondItem->id]);

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 3000,
            'receipt_date' => '2026-03-10',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(2000.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-20')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_deposit_payment'] ?? ''),
        );
        $this->assertSame(3000.0, (float) ($memberRow['second_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-10')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_second_payment'] ?? ''),
        );
        $this->assertNull($memberRow['third_payment']);
        $this->assertNull($memberRow['date_of_third_payment']);
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_excludes_positive_item_tax_from_payment_columns_and_caps_to_package_minus_discount(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-007',
            'name' => 'Manifest Financial Item Tax Exclusion',
            'status' => 'open',
            'price_double' => 3000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-007',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member Item Tax Exclusion', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'full',
            'status' => 'converted',
            'extensions' => [
                [
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => -500,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package full payment with item tax',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 3000,
            'sort_order' => 1,
        ]);

        QuotationItemTax::create([
            'quotation_item_id' => $quotationItem->id,
            'name' => 'Item Tax',
            'calculation_mode' => 'fixed',
            'calculation_value' => 210,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Paid invoice with item tax',
            'amount' => 2710,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(0.0, (float) ($memberRow['discount'] ?? 0));
        $this->assertSame(3000.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertNull($memberRow['second_payment']);
        $this->assertNull($memberRow['third_payment']);
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_splits_receipt_amounts_by_carried_members_in_each_receipt(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-002',
            'name' => 'Manifest Financial Shared Receipt Snapshot',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-002',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $memberUsers = [
            User::factory()->create(['name' => 'Shared Receipt Member One']),
            User::factory()->create(['name' => 'Shared Receipt Member Two']),
            User::factory()->create(['name' => 'Shared Receipt Member Three']),
        ];

        $members = collect($memberUsers)->map(function (User $user) use ($confirmation) {
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => false,
                'status' => 'confirmed',
                'relationship' => 'member',
                'sharing_plan' => 'double',
            ]);
        })->values();

        $primaryMember = $members->first();

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $primaryMember->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $primaryMember->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $quotation->update([
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => -300,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotationItems = $members->map(function (CustomerConfirmationMember $member, int $index) use ($quotation) {
            return QuotationItem::create([
                'quotation_id' => $quotation->id,
                'customer_confirmation_member_id' => $member->id,
                'description' => 'Installment member #'.($index + 1),
                'is_header' => false,
                'quantity' => 1,
                'rate' => 5000,
                'sort_order' => $index + 1,
            ]);
        });

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit invoice',
            'extensions' => [
                [
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 1500,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 1500,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second invoice',
            'extensions' => [
                [
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 3000,
            'invoice_date' => '2026-03-10',
            'due_date' => '2026-03-10',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 3000,
            'receipt_date' => '2026-03-10',
            'payment_method' => 'transfer',
        ]);

        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Final invoice',
            'extensions' => [
                [
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 600,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-03-20',
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $thirdInvoice->id,
            'amount' => 600,
            'receipt_date' => '2026-03-20',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $memberRow = collect($rehydrated['members'])
            ->firstWhere('customer_confirmation_member_id', $primaryMember->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(300.0, (float) ($memberRow['discount'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-01')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_deposit_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['deposit_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-10')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_second_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['second_payment'] ?? 0));
        $this->assertSame(
            \Carbon\Carbon::parse('2026-03-20')->translatedFormat('d F Y'),
            (string) ($memberRow['date_of_third_payment'] ?? ''),
        );
        $this->assertSame(4900.0, (float) ($memberRow['third_payment'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_allocates_group_discount_to_payer_before_spillover(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-RATIO-001',
            'name' => 'Manifest Discount Ratio Package',
            'status' => 'open',
            'price_double' => 9000,
            'price_single' => 4500,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-RATIO-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $members = collect([
            ['name' => 'Ratio Member One', 'sharing_plan' => 'double'],
            ['name' => 'Ratio Member Two', 'sharing_plan' => 'double'],
            ['name' => 'Ratio Member Three', 'sharing_plan' => 'single'],
        ])->map(function (array $row) use ($confirmation): CustomerConfirmationMember {
            $user = User::factory()->create(['name' => $row['name']]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => false,
                'status' => 'confirmed',
                'relationship' => 'member',
                'sharing_plan' => $row['sharing_plan'],
            ]);
        })->values();

        foreach ($members as $index => $member) {
            ManifestMember::create([
                'manifest_id' => $manifest->id,
                'customer_confirmation_member_id' => $member->id,
                'sharing_plan' => $member->sharing_plan,
                'sort_order' => $index + 1,
            ]);
        }

        $quotation = Quotation::create([
            'customer_id' => (int) $members[0]->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'full',
            'status' => 'converted',
            'extensions' => [
                [
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => -500,
                    'sort_order' => 1,
                ],
            ],
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $members[0]->id,
            'description' => 'Member 1 line',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 9010,
            'sort_order' => 1,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $members[1]->id,
            'description' => 'Member 2 line',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 8990,
            'sort_order' => 2,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $members[2]->id,
            'description' => 'Member 3 line',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 4500,
            'sort_order' => 3,
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $memberRows = collect($rehydrated['members'])->keyBy('customer_confirmation_member_id');

        $this->assertSame(0.0, (float) ($memberRows[$members[0]->id]['discount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRows[$members[1]->id]['discount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRows[$members[2]->id]['discount'] ?? 0));
    }

    public function test_get_for_edit_show_uses_exact_third_payment_remainder_for_fully_paid_shared_installment_members(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-REMAINDER-001',
            'name' => 'Manifest Exact Third Payment Package',
            'status' => 'open',
            'price_double' => 9000,
            'price_single' => 4500,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-REMAINDER-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $members = collect([
            ['name' => 'Remainder Member One', 'sharing_plan' => 'double'],
            ['name' => 'Remainder Member Two', 'sharing_plan' => 'double'],
            ['name' => 'Remainder Member Three', 'sharing_plan' => 'single'],
        ])->map(function (array $row) use ($confirmation): CustomerConfirmationMember {
            $user = User::factory()->create(['name' => $row['name']]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => false,
                'status' => 'confirmed',
                'relationship' => 'member',
                'sharing_plan' => $row['sharing_plan'],
            ]);
        })->values();

        foreach ($members as $index => $member) {
            ManifestMember::create([
                'manifest_id' => $manifest->id,
                'customer_confirmation_member_id' => $member->id,
                'sharing_plan' => $member->sharing_plan,
                'sort_order' => $index + 1,
            ]);
        }

        $quotation = Quotation::create([
            'customer_id' => (int) $members[0]->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => '2026-04-01',
            'expiry_date' => '2026-04-30',
            'payment_plan' => 'installment',
            'status' => 'converted',
            'extensions' => [
                [
                    'name' => 'Main Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => -500,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotationItems = collect([
            ['member_id' => $members[0]->id, 'rate' => 9010],
            ['member_id' => $members[1]->id, 'rate' => 8990],
            ['member_id' => $members[2]->id, 'rate' => 4500],
        ])->map(function (array $row, int $index) use ($quotation): QuotationItem {
            return QuotationItem::create([
                'quotation_id' => $quotation->id,
                'customer_confirmation_member_id' => $row['member_id'],
                'description' => 'Installment member #'.($index + 1),
                'is_header' => false,
                'quantity' => 1,
                'rate' => $row['rate'],
                'sort_order' => $index + 1,
            ]);
        });

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit invoice',
            'extensions' => [
                [
                    'name' => 'First Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 30,
                    'amount' => -30,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 1480,
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 1480,
            'receipt_date' => '2026-04-01',
            'payment_method' => 'transfer',
        ]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second invoice',
            'extensions' => [
                [
                    'name' => 'Second Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 250,
                    'amount' => -250,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 11000,
            'invoice_date' => '2026-04-10',
            'due_date' => '2026-04-10',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 11000,
            'receipt_date' => '2026-04-10',
            'payment_method' => 'transfer',
        ]);

        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Final invoice',
            'extensions' => [
                [
                    'name' => 'Third Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 220,
                    'amount' => -220,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 9530,
            'invoice_date' => '2026-04-20',
            'due_date' => '2026-04-20',
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $thirdInvoice->id,
            'amount' => 9530,
            'receipt_date' => '2026-04-20',
            'payment_method' => 'transfer',
        ]);

        CustomerConfirmationMember::query()
            ->whereIn('id', $members->pluck('id')->all())
            ->update(['status' => 'fully_paid']);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $memberRows = collect($rehydrated['members'])->keyBy('customer_confirmation_member_id');

        $this->assertSame(500.0, (float) ($memberRows[$members[0]->id]['discount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRows[$members[1]->id]['discount'] ?? 0));
        $this->assertSame(0.0, (float) ($memberRows[$members[2]->id]['discount'] ?? 0));

        foreach ($members as $member) {
            $row = $memberRows[$member->id] ?? null;

            $this->assertNotNull($row);

            $payableAmount = round(
                max(
                    (float) ($row['package_price'] ?? 0) - (float) ($row['discount'] ?? 0),
                    0,
                ),
                2,
            );
            $depositAmount = (float) ($row['deposit_payment'] ?? 0);
            $secondAmount = (float) ($row['second_payment'] ?? 0);
            $thirdAmount = (float) ($row['third_payment'] ?? 0);

            $this->assertGreaterThanOrEqual(0, $thirdAmount);
            $this->assertGreaterThanOrEqual(
                $payableAmount,
                round($depositAmount + $secondAmount + $thirdAmount, 2),
            );
            $this->assertSame(0.0, (float) ($row['balance_due'] ?? 0));
        }
    }

    public function test_patch_manifest_core_section_updates_manifest_fields_and_package_status(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-SECTION-CORE-001',
            'name' => 'Section Core Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-SECTION-CORE-001',
            'notes' => 'Initial notes',
        ]);
        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'name' => 'Section Core Official',
            'type' => 'mutawif',
        ]);

        $this->patchJson(route('manifests.sections.core.update', [
            'manifestId' => $manifest->id,
        ]), [
            'notes' => 'Core section updated notes',
            'in_charge_official_id' => $official->id,
            'status' => 'closed',
        ])->assertOk()
            ->assertJsonPath('message', 'Manifest core section updated successfully.');

        $manifest->refresh();
        $package->refresh();

        $this->assertSame('Core section updated notes', $manifest->notes);
        $this->assertSame($official->id, $manifest->in_charge_official_id);
        $this->assertSame('closed', $package->status);
    }

    public function test_patch_manifest_sharing_groups_section_updates_member_data(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patchJson(route('manifests.sections.sharing-groups.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_sharing_groups' => [
                [
                    'id' => null,
                    'customer_confirmation_id' => $member->customer_confirmation_id,
                    'sort_order' => 1,
                    'group_relationship' => 'Family',
                    'remarks' => 'Section endpoint group',
                    'members' => [
                        [
                            'id' => $manifestMember->id,
                            'customer_confirmation_member_id' => $member->id,
                            'relationship' => 'husband',
                            'sharing_plan' => 'double',
                            'sort_order' => 1,
                            'patch' => [
                                'passport_number' => 'PATCH-SHARE-001',
                                'name_as_per_passport' => 'Section Member',
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Manifest sharing-groups section updated successfully.');

        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'passport_number' => 'PATCH-SHARE-001',
            'relationship' => 'husband',
        ]);
    }

    public function test_patch_manifest_rooms_section_updates_room_and_members(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patchJson(route('manifests.sections.rooms.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_rooms' => [
                [
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'group_relationship' => 'Family',
                    'room_label' => 'Section Room 1',
                    'room_number' => 'SEC-501',
                    'room_type' => 'double',
                    'bed_type' => 'king',
                    'sharing_plan' => 'double',
                    'meal' => 'Breakfast',
                    'remarks' => 'Section room remarks',
                    'members' => [
                        [
                            'manifest_member_id' => $manifestMember->id,
                            'customer_confirmation_member_id' => $member->id,
                            'sort_order' => 1,
                            'remarks' => 'Section member remarks',
                        ],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Manifest rooms section updated successfully.');

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'room_number' => 'SEC-501',
            'room_label' => 'Section Room 1',
            'remarks' => 'Section room remarks',
        ]);

        $roomId = (int) Manifest::query()->findOrFail($manifest->id)
            ->rooms()
            ->value('id');

        $this->assertDatabaseHas('manifest_room_members', [
            'manifest_room_id' => $roomId,
            'manifest_member_id' => $manifestMember->id,
            'sort_order' => 1,
            'remarks' => 'Section member remarks',
        ]);
    }

    public function test_patch_manifest_rooms_section_validate_only_does_not_persist_updates(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patchJson(route('manifests.sections.rooms.update', [
            'manifestId' => $manifest->id,
        ]), [
            'validate_only' => true,
            'manifest_rooms' => [
                [
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'relationship' => 'Family',
                    'room_label' => 'Validate Only Room',
                    'room_number' => 'VAL-501',
                    'room_type' => 'double',
                    'bed_type' => 'king',
                    'sharing_plan' => 'double',
                    'meal' => 'Breakfast',
                    'members' => [
                        [
                            'manifest_member_id' => $manifestMember->id,
                            'customer_confirmation_member_id' => $member->id,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('validated', true);

        $this->assertDatabaseMissing('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'room_number' => 'VAL-501',
        ]);
    }

    public function test_patch_manifest_documents_section_updates_manifest_documents(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patch(route('manifests.sections.documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'documents' => [
                'train_tickets' => [
                    [
                        'file' => UploadedFile::fake()->create('section-train.pdf', 100, 'application/pdf'),
                        'file_name' => 'Section Train Ticket.pdf',
                    ],
                ],
                'flight_tickets' => [
                    [
                        'file' => UploadedFile::fake()->create('section-flight.pdf', 100, 'application/pdf'),
                        'file_name' => 'Section Flight Ticket.pdf',
                    ],
                ],
                'visa' => [],
                'hotel' => [],
                'passport' => [],
                'photo' => [],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'flight_tickets',
            'file_name' => 'Section Flight Ticket.pdf',
        ]);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'train_tickets',
            'file_name' => 'Section Train Ticket.pdf',
        ]);
    }

    public function test_patch_manifest_documents_section_allows_missing_documents_payload(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patchJson(route('manifests.sections.documents.update', [
            'manifestId' => $manifest->id,
        ]), [])->assertOk()
            ->assertJsonPath('message', 'Manifest documents section updated successfully.');
    }

    public function test_patch_manifest_receipt_documents_section_adds_manifest_member_receipts(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patch(route('manifests.sections.receipt-documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_member_receipts' => [
                (string) $manifestMember->id => [
                    [
                        'file' => UploadedFile::fake()->create('receipt-add.pdf', 100, 'application/pdf'),
                        'file_name' => 'Receipt Added.pdf',
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Manifest receipt-documents section updated successfully.');

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $manifestMember->id,
            'field' => 'receipt',
            'file_name' => 'Receipt Added.pdf',
        ]);

        $storedPath = (string) ModelFile::query()
            ->where('fileable_type', ManifestMember::class)
            ->where('fileable_id', $manifestMember->id)
            ->where('field', 'receipt')
            ->value('file_path');

        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_patch_manifest_receipt_documents_section_accepts_manifest_member_receipts_key(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patch(route('manifests.sections.receipt-documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_member_receipts' => [
                (string) $manifestMember->id => [
                    [
                        'file' => UploadedFile::fake()->create('member-receipt-add.pdf', 100, 'application/pdf'),
                        'file_name' => 'Member Receipt Added.pdf',
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $manifestMember->id,
            'field' => 'receipt',
            'file_name' => 'Member Receipt Added.pdf',
        ]);
    }

    public function test_patch_manifest_receipt_documents_section_accepts_list_payload_shape(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patch(route('manifests.sections.receipt-documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_member_receipts' => [
                [
                    'manifest_member_id' => $manifestMember->id,
                    'customer_confirmation_member_id' => $member->id,
                    'receipt_documents' => [
                        [
                            'file' => UploadedFile::fake()->create('receipt-list-shape.pdf', 100, 'application/pdf'),
                            'file_name' => 'Receipt List Shape.pdf',
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $manifestMember->id,
            'field' => 'receipt',
            'file_name' => 'Receipt List Shape.pdf',
        ]);
    }

    public function test_patch_manifest_receipt_documents_section_allows_empty_payload(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patchJson(route('manifests.sections.receipt-documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_member_receipts' => [],
        ])->assertOk()
            ->assertJsonPath('message', 'Manifest receipt-documents section updated successfully.');
    }

    public function test_patch_manifest_rooms_section_accepts_manifest_member_id_alias(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patchJson(route('manifests.sections.rooms.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_rooms' => [
                [
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'relationship' => 'Family',
                    'room_label' => 'Member Alias Room',
                    'room_number' => 'MEM-501',
                    'room_type' => 'double',
                    'bed_type' => 'king',
                    'sharing_plan' => 'double',
                    'meal' => 'Breakfast',
                    'members' => [
                        [
                            'manifest_member_id' => $manifestMember->id,
                            'customer_confirmation_member_id' => $member->id,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'room_number' => 'MEM-501',
        ]);

        $roomId = (int) Manifest::query()->findOrFail($manifest->id)
            ->rooms()
            ->value('id');

        $this->assertDatabaseHas('manifest_room_members', [
            'manifest_room_id' => $roomId,
            'manifest_member_id' => $manifestMember->id,
        ]);
    }

    public function test_get_for_edit_show_canonical_payload_preserves_member_role_and_room_member_ids(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $confirmationMember, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->patchJson(route('manifests.sections.rooms.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_rooms' => [
                [
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'relationship' => 'Family',
                    'room_label' => 'Canonical Identity Room',
                    'room_number' => 'CID-501',
                    'room_type' => 'double',
                    'bed_type' => 'queen',
                    'sharing_plan' => 'double',
                    'meal' => 'Breakfast',
                    'members' => [
                        [
                            'manifest_member_id' => $manifestMember->id,
                            'customer_confirmation_member_id' => $confirmationMember->id,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $result = app(ManifestService::class)->getForEditShow($manifest->id);

        $canonicalGroupMember = $result['manifest_sharing_groups'][0]['members'][0] ?? null;
        $this->assertIsArray($canonicalGroupMember);
        $this->assertSame($manifestMember->id, $canonicalGroupMember['id']);
        $this->assertSame($confirmationMember->id, $canonicalGroupMember['customer_confirmation_member_id']);
        $this->assertSame('member', $canonicalGroupMember['relationship']);

        $legacyRoomMember = $result['rooms'][0]['members'][0] ?? null;
        $canonicalRoomMember = $result['manifest_rooms'][0]['members'][0] ?? null;

        $this->assertIsArray($legacyRoomMember);
        $this->assertIsArray($canonicalRoomMember);
        $this->assertSame($legacyRoomMember['id'], $canonicalRoomMember['id']);
        $this->assertSame($manifestMember->id, $canonicalRoomMember['manifest_member_id']);
        $this->assertSame($confirmationMember->id, $canonicalRoomMember['customer_confirmation_member_id']);
    }

    public function test_patch_manifest_receipt_documents_section_removes_manifest_member_receipts(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'manifest_member' => $member] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $existingPath = 'manifests/receipt/existing-receipt.pdf';
        Storage::disk('public')->put($existingPath, 'existing-receipt');

        $existingFile = $member->files()->create([
            'field' => 'receipt',
            'file_name' => 'Existing Receipt.pdf',
            'file_path' => $existingPath,
        ]);

        $this->patchJson(route('manifests.sections.receipt-documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_member_receipts' => [
                (string) $member->id => [
                    [
                        'id' => $existingFile->id,
                        'file_path' => $existingPath,
                        'removed' => true,
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('model_files', [
            'id' => $existingFile->id,
        ]);
        Storage::disk('public')->assertMissing($existingPath);
    }

    public function test_patch_manifest_receipt_documents_section_replaces_manifest_member_receipts(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'manifest_member' => $member] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $oldPath = 'manifests/receipt/old-receipt.pdf';
        Storage::disk('public')->put($oldPath, 'old-receipt');

        $oldFile = $member->files()->create([
            'field' => 'receipt',
            'file_name' => 'Old Receipt.pdf',
            'file_path' => $oldPath,
        ]);

        $this->patch(route('manifests.sections.receipt-documents.update', [
            'manifestId' => $manifest->id,
        ]), [
            'manifest_member_receipts' => [
                (string) $member->id => [
                    [
                        'id' => $oldFile->id,
                        'file_path' => $oldPath,
                        'removed' => true,
                    ],
                    [
                        'file' => UploadedFile::fake()->create('receipt-replacement.pdf', 100, 'application/pdf'),
                        'file_name' => 'Replacement Receipt.pdf',
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('model_files', [
            'id' => $oldFile->id,
        ]);
        Storage::disk('public')->assertMissing($oldPath);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => ManifestMember::class,
            'fileable_id' => $member->id,
            'field' => 'receipt',
            'file_name' => 'Replacement Receipt.pdf',
        ]);

        $newPath = (string) ModelFile::query()
            ->where('fileable_type', ManifestMember::class)
            ->where('fileable_id', $member->id)
            ->where('field', 'receipt')
            ->value('file_path');

        Storage::disk('public')->assertExists($newPath);
    }

    public function test_store_update_ignores_payments_payload_after_manifest_payment_contract_removal(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'confirmation_member' => $member, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $manifest->package_id,
            'status' => 'draft',
            'payments' => [
                [
                    'manifest_member_id' => $manifestMember->id,
                    'member_name' => 'Frozen Member Updated',
                    'description' => 'Attempted update while frozen',
                    'amount' => 5000,
                    'paid_amount' => 5000,
                    'outstanding_amount' => 0,
                    'payment_date' => '2026-03-22',
                    'status' => 'paid',
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $manifestData = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertArrayNotHasKey('payments', $manifestData);
    }

    public function test_manifest_payment_routes_are_removed_from_active_contract(): void
    {
        $this->assertFalse(Route::has('manifests.payments.store'));
        $this->assertFalse(Route::has('manifests.payments.update'));
        $this->assertFalse(Route::has('manifests.payments.destroy'));
    }

    public function test_get_for_edit_show_no_longer_exposes_manifest_payments_contract(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        ['manifest' => $manifest, 'manifest_member' => $manifestMember] = $this->createManifestWithSingleMemberFixture($actingUser->id);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $manifest->package_id,
            'status' => 'draft',
            'payments' => [
                [
                    'manifest_member_id' => $manifestMember->id,
                    'member_name' => 'Frozen Member Updated',
                    'description' => 'Attempted update while frozen',
                    'amount' => 9999,
                    'paid_amount' => 9999,
                    'outstanding_amount' => 0,
                    'payment_date' => '2026-03-22',
                    'status' => 'paid',
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $manifestData = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertArrayNotHasKey('payments', $manifestData);
    }

    private function createMemberForPackage(int $packageId, string $customerName, int $createdBy, ?int $confirmationId = null): CustomerConfirmationMember
    {
        $user = User::factory()->create(['name' => $customerName]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        if ($confirmationId === null) {
            $confirmation = CustomerConfirmation::create([
                'package_id' => $packageId,
                'package_room_type' => 'double',
                'package_category' => 'classic_umrah',
                'created_by' => $createdBy,
            ]);

            $confirmationId = $confirmation->id;
        }

        return CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmationId,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'relationship' => 'member',
        ]);
    }

    /**
     * @return array{manifest: Manifest, confirmation_member: CustomerConfirmationMember, manifest_member: ManifestMember}
     */
    private function createManifestWithSingleMemberFixture(int $createdBy): array
    {
        $package = Package::create([
            'package_number' => 'PKG-SECTION-FIXTURE-'.uniqid(),
            'name' => 'Section Fixture Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-SECTION-FIXTURE-'.uniqid(),
            'status' => 'draft',
        ]);

        $confirmationMember = $this->createMemberForPackage($package->id, 'Section Fixture Member', $createdBy);

        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'package_id' => $package->id,
            'status' => 'draft',
            'members' => [
                [
                    'customer_confirmation_member_id' => $confirmationMember->id,
                    'name_as_per_passport' => 'Section Member',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'fixture-group-1',
                ],
            ],
            'documents' => [
                'train_tickets' => [],
                'flight_tickets' => [],
                'visa' => [],
                'hotel' => [],
                'passport' => [],
                'photo' => [],
            ],
        ])->assertRedirect(route('manifests.index'));

        $manifestMember = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $confirmationMember->id)
            ->firstOrFail();

        return [
            'manifest' => $manifest,
            'confirmation_member' => $confirmationMember,
            'manifest_member' => $manifestMember,
        ];
    }
}
