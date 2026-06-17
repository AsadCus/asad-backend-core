<?php

namespace Tests\Feature\Tms;

use App\Models\Customer;
use App\Models\User;
use App\Services\UserRoles\CustomerUserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class CustomerUserServiceNumberingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('customer', 'web');
    }

    public function test_store_generates_customer_number_when_missing(): void
    {
        $user = app(CustomerUserService::class)->store([
            'name' => 'Generated Customer',
            'email' => 'generated.customer@example.com',
            'contact' => '0123456789',
        ]);

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^CUST-\d{4}-\d{4}$/',
            (string) $customer->customer_number,
        );
    }

    public function test_update_accepts_manual_customer_number_matching_format(): void
    {
        $service = app(CustomerUserService::class);

        $user = $service->store([
            'name' => 'Manual Customer',
            'email' => 'manual.customer@example.com',
            'contact' => '0198765432',
        ]);

        $manualNumber = 'CUST-'.now()->format('Y').'-9999';

        $service->update([
            'name' => 'Manual Customer Updated',
            'email' => 'manual.customer@example.com',
            'contact' => '0198765432',
            'customer_number' => $manualNumber,
        ], $user->id);

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($manualNumber, (string) $customer->customer_number);
    }

    public function test_update_without_document_payload_preserves_existing_customer_documents(): void
    {
        Storage::fake('public');

        $service = app(CustomerUserService::class);

        $user = $service->store([
            'name' => 'Document Preserve Customer',
            'email' => 'document-preserve@example.com',
            'contact' => '0181111222',
            'passport_documents' => [
                [
                    'file' => UploadedFile::fake()->create('preserve-passport.pdf', 120, 'application/pdf'),
                    'file_name' => 'Preserve Passport',
                ],
            ],
        ]);

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        $existingFile = $customer->files()->where('field', 'passport')->firstOrFail();

        $service->update([
            'name' => 'Document Preserve Customer Updated',
            'email' => 'document-preserve@example.com',
            'contact' => '0181111222',
        ], $user->id);

        $customer->refresh();

        $this->assertDatabaseHas('model_files', [
            'id' => $existingFile->id,
            'fileable_type' => Customer::class,
            'fileable_id' => $customer->id,
            'field' => 'passport',
        ]);

        Storage::disk('public')->assertExists((string) $existingFile->file_path);
    }

    public function test_update_document_removal_does_not_delete_physical_file_if_still_referenced(): void
    {
        Storage::fake('public');

        $service = app(CustomerUserService::class);

        $user = $service->store([
            'name' => 'Shared File Primary',
            'email' => 'shared-file-primary@example.com',
            'contact' => '0183333444',
            'passport_documents' => [
                [
                    'file' => UploadedFile::fake()->create('shared-passport.pdf', 120, 'application/pdf'),
                    'file_name' => 'Shared Passport',
                ],
            ],
        ]);

        $primaryCustomer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        $primaryFile = $primaryCustomer->files()->where('field', 'passport')->firstOrFail();

        $secondaryUser = User::factory()->create([
            'name' => 'Shared File Secondary',
            'email' => 'shared-file-secondary@example.com',
        ]);

        $secondaryUser->assignRole('customer');

        $secondaryCustomer = Customer::create([
            'user_id' => $secondaryUser->id,
            'customer_number' => 'CUST-SHARED-0001',
        ]);

        $secondaryCustomer->files()->create([
            'field' => 'passport',
            'file_name' => 'Shared Passport Secondary',
            'file_path' => $primaryFile->file_path,
        ]);

        $service->update([
            'name' => 'Shared File Primary Updated',
            'email' => 'shared-file-primary@example.com',
            'contact' => '0183333444',
            'passport_documents' => [],
        ], $user->id);

        $this->assertDatabaseMissing('model_files', [
            'id' => $primaryFile->id,
            'fileable_type' => Customer::class,
            'fileable_id' => $primaryCustomer->id,
            'field' => 'passport',
        ]);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Customer::class,
            'fileable_id' => $secondaryCustomer->id,
            'field' => 'passport',
            'file_path' => $primaryFile->file_path,
        ]);

        Storage::disk('public')->assertExists((string) $primaryFile->file_path);
    }
}
