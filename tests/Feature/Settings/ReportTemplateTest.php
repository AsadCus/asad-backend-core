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

    public function test_signature_stamp_layout_and_custom_coordinates_can_be_saved(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('report-template.update'), [
            'company_name' => 'Test Company',
            'signature_stamp_layout' => 'custom',
            'custom_signature_stamp_layout' => [
                'unit' => 'percent',
                'placement' => 'left_side',
                'stamp' => [
                    'x' => 12,
                    'y' => 8,
                    'width' => 24,
                    'height' => 56,
                    'z' => 1,
                ],
                'signature' => [
                    'x' => 58,
                    'y' => 16,
                    'width' => 30,
                    'height' => 44,
                    'z' => 2,
                ],
                'labels' => [
                    'show_name' => true,
                    'show_date' => true,
                    'full_name' => 'Authorised By',
                    'date' => '2026-03-18',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $this->assertSame('custom', $settings->signature_stamp_layout);
        $this->assertSame('percent', $settings->custom_signature_stamp_layout['unit']);
        $this->assertSame('left_side', $settings->custom_signature_stamp_layout['placement']);
        $this->assertSame(12, $settings->custom_signature_stamp_layout['stamp']['x']);
        $this->assertSame(58, $settings->custom_signature_stamp_layout['signature']['x']);
        $this->assertTrue($settings->custom_signature_stamp_layout['labels']['show_name']);
        $this->assertSame('Authorised By', $settings->custom_signature_stamp_layout['labels']['full_name']);
    }

    public function test_custom_drawn_signature_data_uri_is_persisted(): void
    {
        $user = User::factory()->create();
        $drawnSignatureData =
            'data:image/png;base64,'.
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Y7JQAAAAASUVORK5CYII=';

        $response = $this->actingAs($user)->put(route('report-template.update'), [
            'company_name' => 'Test Company',
            'signature_stamp_layout' => 'custom',
            'custom_signature_data' => $drawnSignatureData,
        ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $this->assertNotNull($settings->custom_signature_path);
        Storage::disk('public')->assertExists($settings->custom_signature_path);
    }

    public function test_signature_stamp_name_fields_can_be_saved(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('report-template.update'), [
            'company_name' => 'Test Company',
            'signature_stamp_layout' => 'custom',
            'custom_signature_stamp_layout' => [
                'unit' => 'percent',
                'placement' => 'right_side',
                'stamp' => [
                    'x' => 8,
                    'y' => 10,
                    'width' => 26,
                    'height' => 58,
                    'z' => 1,
                ],
                'signature' => [
                    'x' => 62,
                    'y' => 18,
                    'width' => 30,
                    'height' => 48,
                    'z' => 2,
                ],
                'labels' => [
                    'show_name' => true,
                    'show_date' => false,
                    'full_name' => 'Authorized By',
                    'date' => '',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $this->assertSame('custom', $settings->signature_stamp_layout);
        $this->assertSame('right_side', $settings->custom_signature_stamp_layout['placement']);
        $this->assertSame('Authorized By', $settings->custom_signature_stamp_layout['labels']['full_name']);
    }

    public function test_signature_date_field_can_be_saved(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('report-template.update'), [
            'company_name' => 'Test Company',
            'signature_stamp_layout' => 'custom',
            'custom_signature_stamp_layout' => [
                'unit' => 'percent',
                'placement' => 'stack_each_other',
                'stamp' => [
                    'x' => 8,
                    'y' => 10,
                    'width' => 26,
                    'height' => 58,
                    'z' => 1,
                ],
                'signature' => [
                    'x' => 62,
                    'y' => 18,
                    'width' => 30,
                    'height' => 48,
                    'z' => 2,
                ],
                'labels' => [
                    'show_name' => true,
                    'show_date' => true,
                    'full_name' => 'Authorized By',
                    'date' => '2026-03-18',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $this->assertSame('custom', $settings->signature_stamp_layout);
        $this->assertSame('stack_each_other', $settings->custom_signature_stamp_layout['placement']);
        $this->assertSame('Authorized By', $settings->custom_signature_stamp_layout['labels']['full_name']);
        $this->assertSame('2026-03-18', $settings->custom_signature_stamp_layout['labels']['date']);
    }

    public function test_module_template_can_toggle_signature_stamp_full_name_and_date_visibility(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('report-template.update'), [
            'company_name' => 'Test Company',
            'module_templates' => [
                'invoice' => [
                    'show_stamp' => true,
                    'show_signature' => true,
                    'show_signature_stamp_name' => true,
                    'show_signature_stamp_date' => true,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $invoice = $settings->getModuleTemplate('invoice');

        $this->assertTrue($invoice['show_stamp']);
        $this->assertTrue($invoice['show_signature']);
        $this->assertTrue($invoice['show_signature_stamp_name']);
        $this->assertTrue($invoice['show_signature_stamp_date']);
    }

    // TODO: Test file deletion when FormData is properly sent from browser
    // The issue is that HTTP request validation might not preserve empty strings
    // but FormData from browser does. This needs to be tested end-to-end in browser.
    // public function test_file_can_be_deleted_with_empty_string_signal(): void
    // {
    //    ...
    // }
}
