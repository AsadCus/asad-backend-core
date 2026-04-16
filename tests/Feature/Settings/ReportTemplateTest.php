<?php

namespace Tests\Feature\Settings;

use App\Models\ReportSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::findOrCreate('admin', 'web');
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_report_template_page_is_displayed(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->get(route('report-template.edit'));

        $response->assertOk();
    }

    public function test_report_template_preview_shows_payment_history_for_quotation(): void
    {
        $this->assertPaymentHistoryPreviewForModule('quotation');
    }

    public function test_report_template_preview_shows_payment_history_for_invoice(): void
    {
        $this->assertPaymentHistoryPreviewForModule('invoice');
    }

    public function test_report_template_preview_shows_payment_history_for_receipt(): void
    {
        $this->assertPaymentHistoryPreviewForModule('receipt');
    }

    public function test_report_template_settings_can_be_updated(): void
    {
        $user = $this->createAdminUser();

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
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => '',
            ]);

        $response->assertSessionHasErrors('company_name');
    }

    private function assertPaymentHistoryPreviewForModule(string $moduleKey): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)->postJson(route('report-template.preview'), [
            'module_key' => $moduleKey,
        ]);

        $response->assertOk();

        $html = (string) $response->json('html');

        $this->assertStringContainsString('Payment History', $html, "Preview for {$moduleKey} should show Payment History.");
        $this->assertStringContainsString('1st Payment', $html, "Preview for {$moduleKey} should show payment rows.");
    }

    public function test_logo_file_can_be_uploaded(): void
    {
        $user = $this->createAdminUser();
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
        $user = $this->createAdminUser();
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

    public function test_qr_image_can_be_uploaded_and_alignment_persisted(): void
    {
        $user = $this->createAdminUser();
        $qrImage = UploadedFile::fake()->image('qr-image.png', 300, 300);

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => 'Test Company',
                'qr_file' => $qrImage,
                'qr_alignment' => 'right',
            ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $this->assertNotNull($settings->qr_image_path);
        $this->assertSame('right', $settings->qr_alignment);
        Storage::disk('public')->assertExists($settings->qr_image_path);
    }

    public function test_module_show_qr_setting_can_be_updated(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->put(route('report-template.update'), [
                'company_name' => 'Test Company',
                'module_templates' => [
                    'invoice' => [
                        'show_qr' => false,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();

        $settings = ReportSetting::current();
        $invoiceTemplate = $settings->getModuleTemplate('invoice');

        $this->assertFalse((bool) ($invoiceTemplate['show_qr'] ?? true));
    }

    public function test_report_setting_singleton_pattern_works(): void
    {
        $first = ReportSetting::current();
        $second = ReportSetting::current();

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, $first->id);
        $this->assertEquals(1, ReportSetting::count());
    }

    public function test_report_preview_templates_hide_updated_generated_and_receipt_remarks(): void
    {
        $templatePaths = [
            resource_path('views/layout-report.blade.php'),
            resource_path('views/quotations/report-content.blade.php'),
            resource_path('views/invoices/report-content.blade.php'),
            resource_path('views/receipts/report-content.blade.php'),
            resource_path('views/sales/report-content.blade.php'),
            resource_path('views/packages/report-content.blade.php'),
            resource_path('views/manifests/arabic-names-report-content.blade.php'),
            resource_path('views/manifests/airline-names-report-content.blade.php'),
            resource_path('views/manifests/namelist-course-items-report-content.blade.php'),
            resource_path('views/manifests/room-check-report-content.blade.php'),
            resource_path('views/ops-movements/report-content.blade.php'),
            resource_path('views/ops-movements/pif-report-content.blade.php'),
            resource_path('views/ops-movements/budget-report-content.blade.php'),
            resource_path('views/reports/dashboard-payment-summary.blade.php'),
        ];

        foreach ($templatePaths as $templatePath) {
            $this->assertFileExists($templatePath);

            $contents = (string) file_get_contents($templatePath);

            $this->assertStringNotContainsString('UPDATED:', $contents, "Unexpected UPDATED label in {$templatePath}");
            $this->assertStringNotContainsString('updated-date', $contents, "Unexpected updated-date usage in {$templatePath}");
            $this->assertStringNotContainsString('Generated Date', $contents, "Unexpected Generated Date label in {$templatePath}");
            $this->assertStringNotContainsString('Generated on', $contents, "Unexpected Generated note in {$templatePath}");
            $this->assertStringNotContainsString('>Generated<', $contents, "Unexpected Generated column label in {$templatePath}");
        }

        $quotationTemplate = (string) file_get_contents(resource_path('views/quotations/report-content.blade.php'));
        $invoiceTemplate = (string) file_get_contents(resource_path('views/invoices/report-content.blade.php'));
        $receiptTemplate = (string) file_get_contents(resource_path('views/receipts/report-content.blade.php'));

        $this->assertStringContainsString('class="totals-table"', $quotationTemplate);
        $this->assertStringContainsString('class="totals-table"', $invoiceTemplate);
        $this->assertStringContainsString('class="totals-table"', $receiptTemplate);
        $this->assertStringContainsString('!empty($branding[\'footer_text\'])', $quotationTemplate);
        $this->assertStringContainsString('$activeNotes = collect($data[\'notes\'] ?? [])', $quotationTemplate);
        $this->assertStringContainsString('$activeNotes = collect($data[\'notes\'] ?? [])', $invoiceTemplate);
        $this->assertStringContainsString('$activeNotes = collect($data[\'notes\'] ?? [])', $receiptTemplate);
        $this->assertStringContainsString("@include('partials.report-notes')", $quotationTemplate);
        $this->assertStringContainsString("@include('partials.report-notes')", $invoiceTemplate);
        $this->assertStringContainsString("@include('partials.report-notes')", $receiptTemplate);
        $this->assertStringContainsString('text-align: right;', $quotationTemplate);
        $this->assertStringContainsString('text-align: right;', $invoiceTemplate);
        $this->assertStringContainsString('text-align: right;', $receiptTemplate);
        $this->assertStringNotContainsString('remarks-section', $receiptTemplate);
        $this->assertStringNotContainsString('remarks-box', $receiptTemplate);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $user = $this->createAdminUser();
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
        $user = $this->createAdminUser();
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
        $user = $this->createAdminUser();

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
    // public function test_file_can_be_deleted_with_empty_string_signal(): void
    // {
    //    ...
    // }
}
