<?php

namespace Database\Seeders;

use App\Models\ReportSetting;
use Illuminate\Database\Seeder;

class ReportSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default report settings (singleton pattern)
        ReportSetting::firstOrCreate(
            ['id' => 1],
            [
                'company_name' => 'Karva Travel Management System',
                'company_address' => "931 Yishun Central 1\n#01-109, Singapore 760931",
                'company_phone' => null,
                'company_email' => null,
                'logo_path' => 'logo-primary.png',
                'footer_text' => "Thank you for your business.\nFor any inquiries, please contact us.",
                'stamp_path' => null,
                'signature_path' => null,
                'brand_color' => '#c05427',
            ]
        );
    }
}
