<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquirySeederWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_confirmed_enquiries_keep_manifest_package_alignment_workflow(): void
    {
        $this->seed(DatabaseSeeder::class);

        $confirmedGeneral = Enquiry::query()
            ->where('type', 'general')
            ->where('status', EnquiryStatus::Confirmed->value)
            ->firstOrFail();

        $this->assertNotNull($confirmedGeneral->package_id);
        $this->assertNotNull($confirmedGeneral->customerConfirmation);
        $this->assertSame(
            $confirmedGeneral->package_id,
            $confirmedGeneral->customerConfirmation?->package_id,
        );

        $confirmedPrivate = Enquiry::query()
            ->where('type', 'private')
            ->where('status', EnquiryStatus::Confirmed->value)
            ->firstOrFail();

        $this->assertNotNull($confirmedPrivate->package_id);
        $this->assertNotNull($confirmedPrivate->customerConfirmation);
        $this->assertSame(
            $confirmedPrivate->package_id,
            $confirmedPrivate->customerConfirmation?->package_id,
        );

        $nonConfirmedPrivate = Enquiry::query()
            ->where('type', 'private')
            ->where('status', '!=', EnquiryStatus::Confirmed->value)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($nonConfirmedPrivate);
        $this->assertNull($nonConfirmedPrivate->package_id);
    }
}
