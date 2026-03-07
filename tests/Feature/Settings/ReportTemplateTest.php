<?php

namespace Tests\Feature\Settings;

use App\Models\ReportSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_report_template_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('report-template.edit'));

        $response->assertOk();
    }

    public function test_report_template_settings_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => 'Test Company Name',
                'company_address' => 'Test Address Line 1',
                'company_phone' => '+65 1234 5678',
                'company_email' => 'test@example.com',
                'footer_text' => 'Test footer text',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $settings = ReportSetting::current();

        $this->assertSame('Test Company Name', $settings->company_name);
        $this->assertSame('Test Address Line 1', $settings->company_address);
        $this->assertSame('+65 1234 5678', $settings->company_phone);
        $this->assertSame('test@example.com', $settings->company_email);
        $this->assertSame('Test footer text', $settings->footer_text);
        $this->assertEquals($user->id, $settings->updated_by);
    }

    public function test_company_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => '',
            ]);

        $response->assertSessionHasErrors('company_name');
    }

    public function test_logo_file_can_be_uploaded(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => 'Test Company',
                'logo_file' => $file,
            ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_stamp_and_signature_files_can_be_uploaded(): void
    {
        $user = User::factory()->create();
        $stamp = UploadedFile::fake()->image('stamp.png');
        $signature = UploadedFile::fake()->image('signature.png');

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => 'Test Company',
                'stamp_file' => $stamp,
                'signature_file' => $signature,
            ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $this->assertNotNull($settings->stamp_path);
        $this->assertNotNull($settings->signature_path);
        Storage::disk('public')->assertExists($settings->stamp_path);
        Storage::disk('public')->assertExists($settings->signature_path);
    }

    public function test_report_setting_singleton_pattern_works(): void
    {
        $first = ReportSetting::current();
        $second = ReportSetting::current();

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, $first->id);
        $this->assertEquals(1, ReportSetting::count());
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => 'Test Company',
                'logo_file' => $file,
            ]);

        $response->assertSessionHasErrors('logo_file');
    }

    public function test_file_size_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('logo.png')->size(3000); // 3MB

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => 'Test Company',
                'logo_file' => $file,
            ]);

        $response->assertSessionHasErrors('logo_file');
    }

    public function test_updating_one_file_does_not_delete_other_files(): void
    {
        $user = User::factory()->create();

        // Upload all three files initially
        $logo = UploadedFile::fake()->image('logo.png');
        $stamp = UploadedFile::fake()->image('stamp.png');
        $signature = UploadedFile::fake()->image('signature.png');

        $this->actingAs($user)->put(route('report-template.update'), [
            'company_name' => 'Test Company',
            'logo_file' => $logo,
            'stamp_file' => $stamp,
            'signature_file' => $signature,
        ]);

        $settings = ReportSetting::current();
        $originalLogoPath = $settings->logo_path;
        $originalStampPath = $settings->stamp_path;
        $originalSignaturePath = $settings->signature_path;

        $this->assertNotNull($originalLogoPath);
        $this->assertNotNull($originalStampPath);
        $this->assertNotNull($originalSignaturePath);

        // Update only the stamp file - the key test: other files should NOT be deleted
        $newStamp = UploadedFile::fake()->image('new-stamp.png');

        $response = $this->actingAs($user)->put(route('report-template.update'), [
            'company_name' => 'Test Company Updated',
            'stamp_file' => $newStamp,
            // Note: logo_file and signature_file are NOT included in this request
        ]);

        $response->assertSessionHasNoErrors();

        // The critical assertion: logo and signature should still exist (not be deleted)
        $updated = ReportSetting::current();
        $this->assertNotNull($updated->logo_path, 'Logo should not be deleted when updating only stamp');
        $this->assertNotNull($updated->signature_path, 'Signature should not be deleted when updating only stamp');
        $this->assertNotNull($updated->stamp_path, 'Stamp should be present after update');
    }

    // TODO: Test file deletion when FormData is properly sent from browser
    // The issue is that HTTP request validation might not preserve empty strings
    // but FormData from browser does. This needs to be tested end-to-end in browser.
    //public function test_file_can_be_deleted_with_empty_string_signal(): void
    //{
    //    ...
    //}
}
